<?php
/**
 * Registriert die REST-API-Endpunkte für Webhooks (Spez 6.0)
 * (Aktualisiert mit API-Logging für n8n-Monitoring)
 * (Aktualisiert, um Calendly-Notizen, Event-Name und Uhrzeit zu akzeptieren)
 * (Aktualisiert mit "UPSERT"-Logik für /create, die auf 'event' (invitee.created/canceled) reagiert)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Registriert die API-Routen beim 'rest_api_init' Hook.
 */
function wpls_register_rest_api_routes() {
    
    $namespace = 'lead-sequencer/v1'; // Spez 6.0

    // --- Plugin API (n8n, Zapier etc.) ---
    
    // POST /leads/create (Erstellt einen neuen Lead ODER AKTUALISIERT, WENN E-MAIL EXISTIERT)
    register_rest_route( $namespace, '/leads/create', array(
        'methods'             => 'POST',
        'callback'            => 'wpls_rest_handle_upsert_lead', // NEUER HANDLER
        'permission_callback' => 'wpls_rest_check_bearer_auth_permission', 
        'args'                => array(
            'email' => array(
                'required'          => true,
                'validate_callback' => 'is_email',
            ),
            // Alle anderen Argumente sind optional und werden im Handler verarbeitet
        ),
    ) );
    
    // GET /leads/find (Findet einen Lead per E-Mail)
    register_rest_route( $namespace, '/leads/find', array(
        'methods'             => 'GET',
        'callback'            => 'wpls_rest_handle_find_lead_by_email',
        'permission_callback' => 'wpls_rest_check_bearer_auth_permission',
        'args'                => array(
            'email' => array(
                'required'          => true,
                'validate_callback' => 'is_email',
            ),
        ),
    ) );
    
    // GET /leads/<id> (Holt einen einzelnen Lead)
    register_rest_route( $namespace, '/leads/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'wpls_rest_handle_get_lead',
        'permission_callback' => 'wpls_rest_check_bearer_auth_permission',
        'args'                => array(
            'id' => array( 'validate_callback' => 'is_numeric' ),
        ),
    ) );
    
    // POST /leads/<id> (Aktualisiert einen einzelnen Lead)
    register_rest_route( $namespace, '/leads/(?P<id>\d+)', array(
        'methods'             => 'POST',
        'callback'            => 'wpls_rest_handle_update_lead',
        'permission_callback' => 'wpls_rest_check_bearer_auth_permission',
        'args'                => array(
            'id' => array( 'validate_callback' => 'is_numeric' ),
            // Argumente für 'update' sind flexibel und werden im Handler geprüft
        ),
    ) );
}
add_action( 'rest_api_init', 'wpls_register_rest_api_routes' );


// --- BERECHTIGUNGS-CALLBACKS (Sicherheit) ---

/**
 * Permission-Callback 1: Sichert die Plugin-API (n8n)
 * (Aktualisiert mit API-Logging)
 */
function wpls_rest_check_bearer_auth_permission( $request ) {
    $options = get_option( 'wpls_settings' );
    $api_key = $options['plugin_api_key'] ?? '';

    if ( empty( $api_key ) ) {
        $error = new WP_Error( 'no_api_key', __( 'Plugin-API ist nicht konfiguriert.', 'wp-lead-sequencer' ), array( 'status' => 501 ) );
        wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
        return $error;
    }
    
    $auth_header = $request->get_header( 'Authorization' );
    if ( empty( $auth_header ) ) {
        $error = new WP_Error( 'no_auth_header', __( 'Authorization-Header fehlt.', 'wp-lead-sequencer' ), array( 'status' => 401 ) );
        wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
        return $error;
    }

    // Prüfen, ob der Header 'Bearer <key>' entspricht
    if ( sscanf( $auth_header, 'Bearer %s', $token ) !== 1 ) {
        $error = new WP_Error( 'invalid_auth_header', __( 'Authorization-Header ist ungültig.', 'wp-lead-sequencer' ), array( 'status' => 401 ) );
        wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
        return $error;
    }
    
    // Zeit-sichere Prüfung
    if ( hash_equals( $api_key, $token ) ) {
        return true;
    }

    $error = new WP_Error( 'invalid_api_key', __( 'Ungültiger API-Schlüssel.', 'wp-lead-sequencer' ), array( 'status' => 403 ) );
    wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
    return $error;
}


// --- API-HANDLER (Logik) ---

/**
 * NEUER HANDLER: "UPSERT" - Findet, DANN aktualisiert oder erstellt
 * Reagiert jetzt auf $params['event']
 */
function wpls_rest_handle_upsert_lead( $request ) {
    $email = $request->get_param( 'email' );
    
    // 1. Versuche, den Lead anhand der E-Mail zu finden
    $lead_id = wpls_find_lead_by_email( $email );
    
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
         $params = $request->get_body_params();
    }
    
    // 2. Event-Logik (neu)
    $event = $params['event'] ?? 'invitee.created'; // Standard auf 'created', wenn nicht gesendet
    $log_details = array();
    
    // 3. Meta-Felder vorbereiten (ALLES was gesendet wird)
    $meta_input = wpls_prepare_meta_input_from_params( $params );


    if ( $lead_id ) {
        // --- LEAD EXISTIERT ---
        // Führe die UPDATE-Logik aus
        
        $log_message = 'Bestehender Lead aktualisiert (via UPSERT).';
        
        if ( $event === 'invitee.created' ) {
            $meta_input['_lead_status'] = 'booked';
            $meta_input['_lead_call_scheduled'] = '1';
            $meta_input['_lead_showed_call'] = ''; // Zurücksetzen, falls er vorher No-Show war
            $log_message = 'Call gebucht (via UPSERT).';
            wpls_create_log_entry( $lead_id, 'call_booked', 'Call gebucht (REST API)', $log_message );
            wpls_send_outbound_webhook( 'n8n_webhook_lead_booked', $lead_id );
            
        } elseif ( $event === 'invitee.canceled' ) {
            $meta_input['_lead_status'] = 'stopped'; // Stoppt die Sequenz
            $meta_input['_lead_call_scheduled'] = '0';
            $meta_input['_lead_showed_call'] = ''; // Zurücksetzen
            $log_message = 'Call storniert (via UPSERT). Sequenz gestoppt.';
            wpls_create_log_entry( $lead_id, 'system_note', 'Call storniert (REST API)', $log_message );
        }

        foreach ( $meta_input as $key => $value ) {
            update_post_meta( $lead_id, $key, $value );
            $log_details[] = $key;
        }
        
        $log_message .= ' Felder: ' . implode( ', ', $log_details );
        
        $response_data = wpls_get_lead_data_for_api( $lead_id );
        wpls_log_api_request( $request, 'Success', 'Lead ' . $lead_id . ' aktualisiert. ' . $log_message );
        return new WP_REST_Response( $response_data, 200 ); // 200 OK

    } else {
        // --- LEAD IST NEU ---
        // Führe die CREATE-Logik aus
        
        $first_name = $params['first_name'] ?? '';
        $last_name = $params['last_name'] ?? '';
        
        // Status basierend auf Event setzen
        $status = 'new';
        $call_scheduled = '0';
        if ( $event === 'invitee.created' ) {
            $status = 'booked';
            $call_scheduled = '1';
        }
        // 'invitee.canceled' für einen neuen Lead macht keinen Sinn, wird ignoriert (bleibt 'new')

        if ( !empty($last_name) && !empty($first_name) ) {
            $title = $last_name . ', ' . $first_name;
        } else {
            $title = $email;
        }
        
        $is_incomplete = ( empty($first_name) && empty($last_name) );
        
        // Stelle sicher, dass die Basis-Felder gesetzt sind
        $meta_input['_lead_first_name'] = $first_name;
        $meta_input['_lead_last_name'] = $last_name;
        $meta_input['_lead_contact_email'] = $email;
        $meta_input['_lead_status'] = $status;
        $meta_input['_lead_is_incomplete'] = $is_incomplete ? '1' : '0';
        $meta_input['_lead_call_scheduled'] = $call_scheduled;
        $meta_input['_lead_follow_ups_sent'] = 0;

        $new_lead_data = array(
            'post_type'   => 'lead',
            'post_status' => 'publish',
            'post_title'  => $title,
            'meta_input'  => $meta_input,
        );

        $post_id = wp_insert_post( $new_lead_data );

        if ( is_wp_error( $post_id ) ) {
            $error = new WP_Error( 'insert_error', $post_id->get_error_message(), array( 'status' => 500 ) );
            wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
            return $error;
        } else {
            wpls_create_log_entry( $post_id, 'system_note', 'Lead erstellt (REST API)', 'Lead wurde über den /leads/create (UPSERT) Endpunkt hinzugefügt.' );
            
            wpls_send_outbound_webhook( 'n8n_webhook_lead_created', $post_id );
            
            $response_data = wpls_get_lead_data_for_api( $post_id );
            wpls_log_api_request( $request, 'Success', 'Lead ' . $post_id . ' erstellt (via UPSERT). Body: ' . json_encode($response_data) );
            return new WP_REST_Response( $response_data, 201 ); // 201 Created
        }
    }
}


/**
 * Handler 3: Lead per E-Mail finden (n8n)
 */
function wpls_rest_handle_find_lead_by_email( $request ) {
    $email = $request->get_param( 'email' );
    $lead_id = wpls_find_lead_by_email( $email );

    if ( $lead_id ) {
        $response_data = wpls_get_lead_data_for_api( $lead_id );
        wpls_log_api_request( $request, 'Success', 'Lead ' . $lead_id . ' gefunden. Body: ' . json_encode($response_data) );
        return new WP_REST_Response( $response_data, 200 );
    } else {
        $error = new WP_Error( 'not_found', __( 'Kein Lead mit dieser E-Mail gefunden.', 'wp-lead-sequencer' ), array( 'status' => 404 ) );
        wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
        return $error;
    }
}

/**
 * Handler 4: Einzelnen Lead holen (n8n)
 */
function wpls_rest_handle_get_lead( $request ) {
    $lead_id = (int) $request->get_param( 'id' );
    
    if ( get_post_type( $lead_id ) !== 'lead' ) {
         $error = new WP_Error( 'not_found', __( 'Lead nicht gefunden.', 'wp-lead-sequencer' ), array( 'status' => 404 ) );
         wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
         return $error;
    }
    
    $response_data = wpls_get_lead_data_for_api( $lead_id );
    wpls_log_api_request( $request, 'Success', 'Lead ' . $lead_id . ' abgerufen.' );
    return new WP_REST_Response( $response_data, 200 );
}

/**
 * Handler 5: Lead aktualisieren (n8n)
 * (Wird jetzt vom UPSERT-Handler intern verwendet)
 */
function wpls_rest_handle_update_lead( $request ) {
    $lead_id = (int) $request->get_param( 'id' );
    
    if ( get_post_type( $lead_id ) !== 'lead' ) {
         $error = new WP_Error( 'not_found', __( 'Lead nicht gefunden.', 'wp-lead-sequencer' ), array( 'status' => 404 ) );
         wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
         return $error;
    }
    
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
         $params = $request->get_body_params();
    }
    
    $meta_input = wpls_prepare_meta_input_from_params( $params );
    
    // Event-Logik HIER AUCH anwenden (falls /leads/<id> direkt genutzt wird)
    $event = $params['event'] ?? '';
    
    if ( $event === 'invitee.created' ) {
        $meta_input['_lead_status'] = 'booked';
        $meta_input['_lead_call_scheduled'] = '1';
        $meta_input['_lead_showed_call'] = ''; 
    } elseif ( $event === 'invitee.canceled' ) {
        $meta_input['_lead_status'] = 'stopped';
        $meta_input['_lead_call_scheduled'] = '0';
        $meta_input['_lead_showed_call'] = '';
    }

    if ( empty( $meta_input ) ) {
        $error = new WP_Error( 'bad_request', __( 'Keine gültigen Felder zum Aktualisieren angegeben.', 'wp-lead-sequencer' ), array( 'status' => 400 ) );
        wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
        return $error;
    }
    
    $log_details = array();

    // Meta-Felder aktualisieren
    foreach ( $meta_input as $key => $value ) {
        update_post_meta( $lead_id, $key, $value );
        $log_details[] = $key;
        
        // Wenn n8n den Status auf "booked" setzt
        if ( $key === '_lead_status' && $value === 'booked' ) {
            wpls_send_outbound_webhook( 'n8n_webhook_lead_booked', $lead_id );
        }
    }
    
    $log_message = 'Felder aktualisiert: ' . implode( ', ', $log_details );
    wpls_create_log_entry( $lead_id, 'system_note', 'Lead aktualisiert (REST API)', $log_message );
    
    $response_data = wpls_get_lead_data_for_api( $lead_id );
    wpls_log_api_request( $request, 'Success', 'Lead ' . $lead_id . ' aktualisiert. ' . $log_message );
    return new WP_REST_Response( $response_data, 200 );
}

/**
 * HILFSFUNKTION: Bereitet das Meta-Input-Array aus den Request-Parametern vor.
 * Wird von UPSERT und UPDATE verwendet.
 * (Aktualisiert auf 'time_call' und 'event' entfernt)
 *
 * @param array $params Die Request-Parameter (von n8n)
 * @return array Das bereinigte Meta-Array
 */
function wpls_prepare_meta_input_from_params( $params ) {
    // Erlaubte "einfache" Felder, die n8n senden darf
    $allowed_simple_keys = array(
        'first_name' => '_lead_first_name',
        'last_name'  => '_lead_last_name',
        'email'      => '_lead_contact_email',
        'company'    => '_lead_company_name',
        'role'       => '_lead_role',
        // 'status' wird jetzt über 'event' gesteuert
        // 'event' wird in der Hauptfunktion behandelt, nicht hier
        
        // Spezifische WordPress-Felder, die auch erlaubt sind (z.B. für No-Show-Reset)
        '_lead_showed_call' => '_lead_showed_call', 
        
        // Calendly/n8n-Felder
        'notes'        => '_lead_calendly_notes',
        'event_type'   => '_lead_calendly_event_name',
        'time_call'    => '_lead_calendly_start_time', // Geändert von uhrzeit_call
    );
    
    $meta_input = array();

    foreach ( $params as $key => $value ) {
        // Wenn der gesendete Key (z.B. 'notes') in unserer Map existiert
        if ( isset( $allowed_simple_keys[$key] ) ) {
            $meta_key = $allowed_simple_keys[$key];
            
            // Bereinigen
            if ($meta_key === '_lead_contact_email') {
                $meta_input[$meta_key] = sanitize_email( $value );
            } elseif ($meta_key === '_lead_calendly_notes') {
                $meta_input[$meta_key] = sanitize_textarea_field( $value );
            } else {
                $meta_input[$meta_key] = sanitize_text_field( $value );
            }
        }
    }
    
    return $meta_input;
}


// --- API-LOGGING-FUNKTION (NEU) ---

/**
 * Erstellt einen neuen API-Log-Eintrag.
 *
 * @param WP_REST_Request $request  Das Request-Objekt.
 * @param string $status   'Success' oder 'Failed'.
 * @param string $details  Details zur Aktion oder Fehlermeldung.
 */
function wpls_log_api_request( $request, $status, $details = '' ) {
    
    $endpoint = $request->get_route();
    $method = $request->get_method();
    $title = $method . ' ' . $endpoint;
    
    // Body für Fehler-Log holen
    if ( $status === 'Failed' && empty( $details ) ) {
        $body = $request->get_body();
        $details = 'Request Body: ' . $body;
    }

    $log_data = array(
        'post_type'    => 'api_log',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $details,
        'meta_input'   => array(
            '_api_log_status'     => $status,
            '_api_log_endpoint'   => $endpoint,
            '_api_log_ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
        ),
    );

    wp_insert_post( $log_data );
}
?>