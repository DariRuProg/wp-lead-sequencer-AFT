<?php
/**
 * Registriert die REST-API-Endpunkte für Webhooks (Spez 6.0)
 * (Aktualisiert mit API-Logging für n8n-Monitoring)
 * (Aktualisiert, um Calendly-Notizen, Event-Name und Uhrzeit zu akzeptieren)
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
    
    // POST /leads/create (Erstellt einen neuen Lead)
    register_rest_route( $namespace, '/leads/create', array(
        'methods'             => 'POST',
        'callback'            => 'wpls_rest_handle_create_lead',
        'permission_callback' => 'wpls_rest_check_bearer_auth_permission', 
        'args'                => array(
            'email' => array(
                'required'          => true,
                'validate_callback' => 'is_email',
            ),
            'first_name' => array( 'sanitize_callback' => 'sanitize_text_field' ),
            'last_name'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
            'company'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
            'role'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
            'status'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
            // NEUE FELDER
            'notes'        => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
            'event_type'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
            'uhrzeit_call' => array( 'sanitize_callback' => 'sanitize_text_field' ),
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
 * Handler 2: Lead erstellen (n8n)
 * (Aktualisiert mit API-Logging und neuen Feldern)
 */
function wpls_rest_handle_create_lead( $request ) {
    
    $email = $request->get_param( 'email' );
    $first_name = $request->get_param( 'first_name' ) ?? '';
    $last_name = $request->get_param( 'last_name' ) ?? '';
    $status = $request->get_param( 'status' ) ?? 'new';

    if ( !empty($last_name) && !empty($first_name) ) {
        $title = $last_name . ', ' . $first_name;
    } else {
        $title = $email;
    }
    
    // Logik für "unvollständig"
    $is_incomplete = ( empty($first_name) && empty($last_name) );

    $new_lead_data = array(
        'post_type'   => 'lead',
        'post_status' => 'publish',
        'post_title'  => $title,
        'meta_input'  => array(
            '_lead_first_name'    => $first_name,
            '_lead_last_name'     => $last_name,
            '_lead_contact_email' => $email,
            '_lead_company_name'  => $request->get_param( 'company' ) ?? '',
            '_lead_role'          => $request->get_param( 'role' ) ?? '',
            '_lead_status'        => $status,
            '_lead_follow_ups_sent' => 0,
            '_lead_is_incomplete' => $is_incomplete ? '1' : '0',
            '_lead_call_scheduled' => ($status === 'booked') ? '1' : '0', // Setze Call gebucht, wenn Status 'booked'
            
            // NEUE FELDER
            '_lead_calendly_event_name' => $request->get_param('event_type') ?? '',
            '_lead_calendly_start_time' => $request->get_param('uhrzeit_call') ?? '',
            '_lead_calendly_notes'      => $request->get_param('notes') ?? '',
        ),
    );

    $post_id = wp_insert_post( $new_lead_data );

    if ( is_wp_error( $post_id ) ) {
        $error = new WP_Error( 'insert_error', $post_id->get_error_message(), array( 'status' => 500 ) );
        wpls_log_api_request( $request, 'Failed', $error->get_error_message() );
        return $error;
    } else {
        wpls_create_log_entry( $post_id, 'system_note', 'Lead erstellt (REST API)', 'Lead wurde über den /leads/create Endpunkt hinzugefügt.' );
        
        // n8n-Webhook auslösen
        wpls_send_outbound_webhook( 'n8n_webhook_lead_created', $post_id );
        
        $response_data = wpls_get_lead_data_for_api( $post_id );
        wpls_log_api_request( $request, 'Success', 'Lead ' . $post_id . ' erstellt. Body: ' . json_encode($response_data) );
        return new WP_REST_Response( $response_data, 201 ); // 201 Created
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
 * (Aktualisiert mit API-Logging und neuen Feldern)
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

    // Aktualisierbare Felder
    $allowed_fields = array(
        '_lead_first_name', '_lead_last_name', '_lead_contact_email', '_lead_role',
        '_lead_company_name', '_lead_company_industry', '_lead_company_address',
        '_lead_contact_phone', '_lead_website', '_lead_status', '_lead_showed_call',
        '_lead_call_scheduled',
        // NEUE FELDER
        '_lead_calendly_event_name', '_lead_calendly_start_time', '_lead_calendly_notes'
    );
    
    $meta_input = array();
    
    // NEU: Parameter für die neuen Felder aus n8n (key-Namen aus deinem Screenshot)
    $param_map = array(
        'notes'        => '_lead_calendly_notes',
        'event_type'   => '_lead_calendly_event_name',
        'uhrzeit_call' => '_lead_calendly_start_time',
    );

    foreach ( $params as $key => $value ) {
        // Alte Felder direkt zuordnen
        if ( in_array( $key, $allowed_fields ) ) {
             if ($key === '_lead_contact_email') {
                $meta_input[$key] = sanitize_email( $value );
            } else {
                $meta_input[$key] = sanitize_text_field( $value );
            }
        }
        // Neue Felder (notes, etc.) auf die Meta-Keys (_lead_calendly_...) mappen
        elseif ( isset( $param_map[$key] ) ) {
            $meta_key = $param_map[$key];
            if ($meta_key === '_lead_calendly_notes') {
                $meta_input[$meta_key] = sanitize_textarea_field( $value );
            } else {
                $meta_input[$meta_key] = sanitize_text_field( $value );
            }
        }
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
            // Setze auch 'call_scheduled', falls es nicht explizit gesendet wurde
            update_post_meta( $lead_id, '_lead_call_scheduled', '1' );
            // Löse den Outbound-Webhook aus
            wpls_send_outbound_webhook( 'n8n_webhook_lead_booked', $lead_id );
        }
    }
    
    $log_message = 'Felder aktualisiert: ' . implode( ', ', $log_details );
    wpls_create_log_entry( $lead_id, 'system_note', 'Lead aktualisiert (REST API)', $log_message );
    
    $response_data = wpls_get_lead_data_for_api( $lead_id );
    wpls_log_api_request( $request, 'Success', 'Lead ' . $lead_id . ' aktualisiert. ' . $log_message );
    return new WP_REST_Response( $response_data, 200 );
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