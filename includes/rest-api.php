<?php
/**
 * Registriert die REST-API-Endpunkte für Webhooks (Spez 6.0)
 * (Bereinigt vom Calendly-Webhook-Endpunkt; n8n nutzt die /leads/update API)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Registriert die API-Routen beim 'rest_api_init' Hook.
 */
function wpls_register_rest_api_routes() {
    
    $namespace = 'lead-sequencer/v1'; // Spez 6.0

    // --- Endpunkt 1: Calendly Webhook (ENTFERNT) ---
    // n8n wird stattdessen die /leads/find und /leads/update Endpunkte verwenden.

    // --- Endpunkt 2: Plugin API (n8n, Zapier etc.) ---
    // Nutzt den "Bearer Token" (Plugin API Key) zur Authentifizierung
    
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
        ),
    ) );
}
add_action( 'rest_api_init', 'wpls_register_rest_api_routes' );


// --- BERECHTIGUNGS-CALLBACKS (Sicherheit) ---

/**
 * Permission-Callback 1: Sichert die Plugin-API (n8n)
 * Prüft auf einen 'Authorization: Bearer <KEY>' Header.
 */
function wpls_rest_check_bearer_auth_permission( $request ) {
    $options = get_option( 'wpls_settings' );
    $api_key = $options['plugin_api_key'] ?? '';

    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_api_key', __( 'Plugin-API ist nicht konfiguriert.', 'wp-lead-sequencer' ), array( 'status' => 501 ) );
    }
    
    $auth_header = $request->get_header( 'Authorization' );
    if ( empty( $auth_header ) ) {
        return new WP_Error( 'no_auth_header', __( 'Authorization-Header fehlt.', 'wp-lead-sequencer' ), array( 'status' => 401 ) );
    }

    // Prüfen, ob der Header 'Bearer <key>' entspricht
    if ( sscanf( $auth_header, 'Bearer %s', $token ) !== 1 ) {
        return new WP_Error( 'invalid_auth_header', __( 'Authorization-Header ist ungültig.', 'wp-lead-sequencer' ), array( 'status' => 401 ) );
    }
    
    // Zeit-sichere Prüfung
    if ( hash_equals( $api_key, $token ) ) {
        return true;
    }

    return new WP_Error( 'invalid_api_key', __( 'Ungültiger API-Schlüssel.', 'wp-lead-sequencer' ), array( 'status' => 403 ) );
}

/**
 * Permission-Callback 2: (ENTFERNT)
 */
// wpls_rest_check_calendly_webhook_permission() wurde entfernt.


// --- API-HANDLER (Logik) ---

/**
 * Handler 1: Calendly Webhook (ENTFERNT)
 */
// wpls_rest_handle_calendly_webhook() wurde entfernt.

/**
 * Handler 2: Lead erstellen (n8n)
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
    
    // NEU: Logik für "unvollständig", wenn n8n nur E-Mail sendet
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
            '_lead_status'        => $status, // Status kann jetzt gesetzt werden
            '_lead_follow_ups_sent' => 0,
            '_lead_is_incomplete' => $is_incomplete ? '1' : '0', // Setze unvollständig-Flag
        ),
    );

    $post_id = wp_insert_post( $new_lead_data );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'insert_error', $post_id->get_error_message(), array( 'status' => 500 ) );
    } else {
        wpls_create_log_entry( $post_id, 'system_note', 'Lead erstellt (REST API)', 'Lead wurde über den /leads/create Endpunkt hinzugefügt.' );
        
        // n8n-Webhook auslösen
        wpls_send_outbound_webhook( 'n8n_webhook_lead_created', $post_id );
        
        $response_data = wpls_get_lead_data_for_api( $post_id );
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
        return new WP_REST_Response( $response_data, 200 );
    } else {
        return new WP_Error( 'not_found', __( 'Kein Lead mit dieser E-Mail gefunden.', 'wp-lead-sequencer' ), array( 'status' => 404 ) );
    }
}

/**
 * Handler 4: Einzelnen Lead holen (n8n)
 */
function wpls_rest_handle_get_lead( $request ) {
    $lead_id = (int) $request->get_param( 'id' );
    
    if ( get_post_type( $lead_id ) !== 'lead' ) {
         return new WP_Error( 'not_found', __( 'Lead nicht gefunden.', 'wp-lead-sequencer' ), array( 'status' => 404 ) );
    }
    
    $response_data = wpls_get_lead_data_for_api( $lead_id );
    return new WP_REST_Response( $response_data, 200 );
}

/**
 * Handler 5: Lead aktualisieren (n8n)
 */
function wpls_rest_handle_update_lead( $request ) {
    $lead_id = (int) $request->get_param( 'id' );
    
    if ( get_post_type( $lead_id ) !== 'lead' ) {
         return new WP_Error( 'not_found', __( 'Lead nicht gefunden.', 'wp-lead-sequencer' ), array( 'status' => 404 ) );
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
        '_lead_call_scheduled' // Hinzugefügt, damit n8n dies setzen kann
    );
    
    $meta_input = array();
    
    foreach ( $params as $key => $value ) {
        if ( in_array( $key, $allowed_fields ) ) {
            if ($key === '_lead_contact_email') {
                $meta_input[$key] = sanitize_email( $value );
            } else {
                $meta_input[$key] = sanitize_text_field( $value );
            }
        }
    }
    
    if ( empty( $meta_input ) ) {
        return new WP_Error( 'bad_request', __( 'Keine gültigen Felder zum Aktualisieren angegeben.', 'wp-lead-sequencer' ), array( 'status' => 400 ) );
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
    
    wpls_create_log_entry( $lead_id, 'system_note', 'Lead aktualisiert (REST API)', 'Felder aktualisiert: ' . implode( ', ', $log_details ) );
    
    $response_data = wpls_get_lead_data_for_api( $lead_id );
    return new WP_REST_Response( $response_data, 200 );
}
?>