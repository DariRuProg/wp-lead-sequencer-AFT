<?php
/**
 * Registriert die REST-API-Endpunkte für Webhooks (Spez 6.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Registriert die API-Routen beim 'rest_api_init' Hook.
 */
function wpls_register_rest_api_routes() {
    
    $namespace = 'lead-sequencer/v1'; // Spez 6.0

    // --- Endpunkt 1: /webhook/calendly (Spez 6.1) ---
    register_rest_route( $namespace, '/webhook/calendly', array(
        'methods'             => 'POST',
        'callback'            => 'wpls_rest_handle_calendly_webhook',
        'permission_callback' => '__return_true', // Öffentlich, da von extern (Calendly) aufgerufen
    ) );

    // --- Endpunkt 2: /lead/create (Anforderung #2) ---
    register_rest_route( $namespace, '/lead/create', array(
        'methods'             => 'POST',
        'callback'            => 'wpls_rest_handle_create_lead',
        'permission_callback' => 'wpls_rest_permission_check', // Erfordert Authentifizierung
        'args'                => array(
            'email' => array(
                'required'          => true,
                'validate_callback' => 'is_email',
            ),
            'first_name' => array(
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'last_name' => array(
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'company' => array(
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'wpls_register_rest_api_routes' );

/**
 * Berechtigungs-Callback für geschützte Endpunkte.
 * Prüft, ob der Benutzer die Berechtigung 'manage_options' hat.
 */
function wpls_rest_permission_check( $request ) {
    // Stellt sicher, dass der Benutzer eingeloggt ist und die Rechte hat
    // Ideal für Zapier (mit der WordPress App) oder interne Tools
    return current_user_can( 'manage_options' );
}

/**
 * Handler für Endpunkt 1: Calendly Webhook (Spez 6.1)
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function wpls_rest_handle_calendly_webhook( $request ) {
    
    // HINWEIS: Dieser Endpunkt ist öffentlich.
    // In einer echten Implementierung MUSS hier eine Sicherheitsprüfung stattfinden,
    // z.B. durch Abgleich eines 'secret' Schlüssels von Calendly.
    
    $body = $request->get_json_params();

    // E-Mail-Adresse aus dem Calendly-Payload holen
    // (Annahme: Calendly sendet { "payload": { "email": "..." } } - muss angepasst werden)
    $email = $body['payload']['email'] ?? '';
    
    if ( empty( $email ) || ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', 'Keine gültige E-Mail im Payload gefunden.', array( 'status' => 400 ) );
    }

    // 1. Lead per E-Mail suchen
    $lead_id = wpls_find_lead_by_email( $email );

    if ( $lead_id ) {
        // 2. Lead gefunden -> Meta aktualisieren (Spez 6.1)
        update_post_meta( $lead_id, '_lead_call_scheduled', '1' );
        update_post_meta( $lead_id, '_lead_status', 'booked' );

        // 3. Loggen
        wpls_create_log_entry( $lead_id, 'call_booked', 'Call gebucht (Calendly)', 'Lead wurde automatisch durch Calendly-Webhook aktualisiert.' );
        
        return new WP_REST_Response( array( 'message' => 'Lead aktualisiert.' ), 200 );
    } else {
        // Optional: Wenn der Lead nicht gefunden wird, einen neuen erstellen?
        // Fürs Erste: Nur eine Notiz zurückgeben.
        return new WP_REST_Response( array( 'message' => 'Kein Lead mit dieser E-Mail gefunden.' ), 200 );
    }
}

/**
 * Handler für Endpunkt 2: Lead erstellen (Anforderung #2)
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function wpls_rest_handle_create_lead( $request ) {
    
    $email = $request->get_param( 'email' ); // Bereits validiert durch 'args'
    $first_name = $request->get_param( 'first_name' ) ?? '';
    $last_name = $request->get_param( 'last_name' ) ?? '';

    // Titel programmatisch setzen
    if ( !empty($last_name) && !empty($first_name) ) {
        $title = $last_name . ', ' . $first_name;
    } else {
        $title = $email;
    }

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
            // Standard-Status
            '_lead_status'        => 'new',
            '_lead_follow_ups_sent' => 0,
        ),
    );

    $post_id = wp_insert_post( $new_lead_data );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'insert_error', $post_id->get_error_message(), array( 'status' => 500 ) );
    } else {
        wpls_create_log_entry( $post_id, 'system_note', 'Lead erstellt (REST API)', 'Lead wurde über den /lead/create Endpunkt hinzugefügt.' );
        return new WP_REST_Response( array( 'message' => 'Lead erstellt.', 'lead_id' => $post_id ), 201 ); // 201 Created
    }
}

/**
 * Hilfsfunktion: Findet einen Lead (Post ID) anhand seiner E-Mail-Adresse.
 *
 * @param string $email
 * @return int|null Die Post ID oder null.
 */
function wpls_find_lead_by_email( $email ) {
    $args = array(
        'post_type'      => 'lead',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => '_lead_contact_email',
                'value'   => $email,
                'compare' => '=',
            ),
        ),
        'fields' => 'ids', // Nur die ID zurückgeben (performant)
    );
    
    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        return $query->posts[0];
    }

    return null;
}
?>