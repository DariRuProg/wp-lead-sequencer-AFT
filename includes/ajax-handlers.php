<?php
/**
 * Registriert alle AJAX-Handler und die Verarbeitung von Admin-Aktionen (z.B. Bulk Actions).
 * (Aktualisiert mit n8n Outbound-Webhook-Triggern)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// --- AJAX-HANDLER FÜR FRONTEND-SHORTCODE (Spez 4.1) ---

// Aktion 1: Neuen Lead hinzufügen
add_action( 'wp_ajax_add_new_lead', 'wpls_ajax_add_new_lead_handler' );
// Aktion 2: Sequenz starten
add_action( 'wp_ajax_start_lead_sequence', 'wpls_ajax_start_lead_sequence_handler' );
// Aktion 3: No-Show markieren
add_action( 'wp_ajax_mark_lead_noshow', 'wpls_ajax_mark_lead_noshow_handler' );


/**
 * AJAX-Handler (Aktion 1): Fügt einen neuen Lead hinzu (Spez 4.1)
 */
function wpls_ajax_add_new_lead_handler() {
    // Sicherheit prüfen
    check_ajax_referer( 'wpls_ajax_nonce', 'security' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
    }

    // Daten validieren
    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Gültige E-Mail-Adresse ist erforderlich.' ) );
    }

    // Lead-Daten vorbereiten
    $first_name = sanitize_text_field( $_POST['first_name'] );
    $last_name  = sanitize_text_field( $_POST['last_name'] );

    // Titel programmatisch setzen (wie in post-types.php / Spez 2.1)
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
            '_lead_first_name' => $first_name,
            '_lead_last_name'  => $last_name,
            '_lead_contact_email' => $email,
            '_lead_company_name' => sanitize_text_field( $_POST['company'] ),
            '_lead_role'         => sanitize_text_field( $_POST['role'] ),
            // Standard-Status
            '_lead_status'       => 'new',
            '_lead_follow_ups_sent' => 0,
        ),
    );

    // Lead erstellen
    $post_id = wp_insert_post( $new_lead_data );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
    } else {
        wpls_create_log_entry( $post_id, 'system_note', 'Lead erstellt', 'Lead wurde über die Frontend-UI hinzugefügt.' );
        
        // n8n-Webhook auslösen
        wpls_send_outbound_webhook( 'n8n_webhook_lead_created', $post_id );
        
        wp_send_json_success( array( 'message' => 'Lead erfolgreich hinzugefügt.' ) );
    }
}

/**
 * AJAX-Handler (Aktion 2): Startet eine Sequenz (Spez 4.1 / 5.1)
 */
function wpls_ajax_start_lead_sequence_handler() {
    check_ajax_referer( 'wpls_ajax_nonce', 'security' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
    }
    
    $lead_id = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
    
    if ( $lead_id > 0 ) {
        wpls_start_sequence_for_lead( $lead_id );
        wp_send_json_success( array( 'message' => 'Sequenz gestartet.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Ungültige Lead-ID' ) );
    }
}

/**
 * AJAX-Handler (Aktion 3): Markiert einen Lead als No-Show (Spez 4.1)
 */
function wpls_ajax_mark_lead_noshow_handler() {
    check_ajax_referer( 'wpls_ajax_nonce', 'security' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
    }
    
    $lead_id = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
    
    if ( $lead_id > 0 ) {
        // Status auf 'no' setzen (Spez 2.1)
        update_post_meta( $lead_id, '_lead_showed_call', 'no' );
        // Cron-Job (wpls_execute_noshow_check) wird dies beim nächsten Lauf aufnehmen
        
        wpls_create_log_entry( $lead_id, 'system_note', 'Als No-Show markiert', 'Wartet auf automatisiertes No-Show-Follow-up.' );
        wp_send_json_success( array( 'message' => 'Als No-Show markiert.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Ungültige Lead-ID' ) );
    }
}


/**
 * ZENTRALE LOGIK: Startet die Sequenz für einen einzelnen Lead.
 * Wird von AJAX (Spez 5.1) oder Bulk Actions (Anf. #4) aufgerufen.
 *
 * @param int $lead_id Die ID des Leads.
 */
function wpls_start_sequence_for_lead( $lead_id ) {
    
    // 1. Prüfen, ob Sequenz schon läuft
    $status = get_post_meta( $lead_id, '_lead_status', true );
    if ( $status === 'sequencing' ) {
        return false; // Läuft bereits
    }

    // 2. Meta-Felder setzen (Spez 5.1)
    $current_time = time();
    update_post_meta( $lead_id, '_lead_started_sequence', '1' );
    update_post_meta( $lead_id, '_lead_sequence_last_date', $current_time ); // Anforderung #5 (Autom. Datum)
    update_post_meta( $lead_id, '_lead_status', 'sequencing' );
    update_post_meta( $lead_id, '_lead_follow_ups_sent', 1 ); // Zähler auf 1 setzen

    // 3. SOFORT E-Mail 1 senden (Anforderung #6)
    $sent = wpls_send_email( $lead_id, 'follow_up_1' );
    
    // 4. Loggen (Spez 5.1)
    if ($sent) {
        wpls_create_log_entry( $lead_id, 'sequence_started', 'Sequenz gestartet', 'Follow Up 1 wurde erfolgreich gesendet.' );
        
        // n8n-Webhook auslösen
        wpls_send_outbound_webhook( 'n8n_webhook_lead_sequence_started', $lead_id );
        
    } else {
        wpls_create_log_entry( $lead_id, 'system_note', 'Sequenz-Start FEHLER', 'Follow Up 1 konnte nicht gesendet werden (Vorlage fehlt?). Sequenz gestoppt.' );
        // WICHTIG: Status zurücksetzen oder auf 'stopped' setzen, wenn die erste E-Mail fehlschlägt
        update_post_meta( $lead_id, '_lead_status', 'stopped' );
    }
    
    return true;
}


// --- VERARBEITUNG VON ADMIN-AKTIONEN (z.B. Bulk Actions) ---

/**
 * Verarbeitet die Bulk-Aktionen von der WP_List_Table (Anf. #4)
 * Wird über 'admin_init' ausgelöst (vor dem Laden der Seite).
 */
function wpls_handle_bulk_actions() {
    
    // 1. Prüfen, ob wir auf unserer CRM-Seite sind
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wpls-main-crm' ) {
        return;
    }

    // 2. Prüfen, welche Aktion ausgeführt wurde
    $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
    if ( empty( $action ) || $action === '-1' ) {
        $action = isset( $_GET['action2'] ) ? $_GET['action2'] : ''; // WP nutzt 'action' und 'action2' (oben/unten)
    }

    // 3. IDs der ausgewählten Leads holen
    $lead_ids = isset( $_GET['lead_ids'] ) ? (array) $_GET['lead_ids'] : array();
    if ( empty( $lead_ids ) ) {
        return; // Nichts ausgewählt
    }
    
    // 4. Aktion verarbeiten
    $count = 0;
    // Wir leiten zur Hauptseite (CRM) weiter
    $redirect_url = admin_url( 'admin.php?page=wpls-main-crm' );

    // --- AKTION: Sequenz starten (Anf. #4) ---
    if ( $action === 'bulk_start_sequence' ) {
        // Nonce verifizieren (WP_List_Table fügt dies automatisch hinzu)
        check_admin_referer( 'bulk-leads' ); 
        
        foreach ( $lead_ids as $lead_id ) {
            if ( wpls_start_sequence_for_lead( (int) $lead_id ) ) {
                $count++;
            }
        }
        
        $redirect_url = add_query_arg(
            array( 'message' => 'sequence_started', 'count' => $count ),
            $redirect_url
        );
        wp_redirect( $redirect_url );
        exit;
    }
    
    // --- AKTION: Löschen (Spez 3.1) ---
    if ( $action === 'bulk_delete' ) {
        check_admin_referer( 'bulk-leads' );
        
        foreach ( $lead_ids as $lead_id ) {
            // In den Papierkorb verschieben
            if ( wp_trash_post( (int) $lead_id ) ) {
                $count++;
            }
        }
        
        $redirect_url = add_query_arg(
            array( 'message' => 'deleted', 'count' => $count ),
            $redirect_url
        );
        wp_redirect( $redirect_url );
        exit;
    }
}
add_action( 'admin_init', 'wpls_handle_bulk_actions' );
?>