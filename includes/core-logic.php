<?php
/**
 * Enthält die Kernlogik des Plugins: Senden, Logging und andere zentrale Funktionen.
 * (Neu generiert, um [redacted] Fehler zu beheben)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// --- LOGGING (Spez 2.3) ---

/**
 * Erstellt einen neuen Log-Eintrag für einen Lead.
 *
 * @param int    $lead_id Die ID des Leads.
 * @param string $type    Der Log-Typ (z.B. 'email_sent', 'call_booked').
 * @param string $title   Der Titel des Log-Eintrag.
 * @param string $details Details zur Aktion.
 * @return int|WP_Error Die ID des neuen Log-Posts oder WP_Error.
 */
function wpls_create_log_entry( $lead_id, $type, $title, $details = '' ) {
    
    $log_data = array(
        'post_type'    => 'lead_log',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $details,
        'meta_input'   => array(
            '_log_lead_id' => $lead_id,
            '_log_type'    => $type,
        ),
    );

    return wp_insert_post( $log_data );
}


// --- E-MAIL SEND ENGINE (Spez 5.4, Angepasst auf wp_mail()) ---

/**
 * ZENTRALE SENDE-FUNKTION
 *
 * Holt eine Vorlage, personalisiert sie und sendet sie via wp_mail().
 * Nutzt automatisch das vom User konfigurierte SMTP-Plugin.
 *
 * @param int    $lead_id       Die ID des Leads.
 * @param string $template_type Der Typ der Vorlage (z.B. 'follow_up_1').
 * @return bool True bei Erfolg, False bei Fehler.
 */
function wpls_send_email( $lead_id, $template_type ) {
    
    // 1. Lead-Daten holen
    $lead_post = get_post( $lead_id );
    if ( ! $lead_post ) {
        return false;
    }
    $lead_meta = get_post_meta( $lead_id );
    $email     = $lead_meta['_lead_contact_email'][0] ?? '';
    $first_name = $lead_meta['_lead_first_name'][0] ?? '';
    
    if ( empty( $email ) || ! is_email( $email ) ) {
        wpls_create_log_entry( $lead_id, 'system_note', 'Sende-Fehler', 'Keine gültige E-Mail-Adresse für diesen Lead.' );
        return false; // Kann nicht ohne E-Mail senden
    }

    // 2. E-Mail-Vorlage holen (Spez 2.2)
    $template = wpls_get_template_by_type( $template_type );
    if ( ! $template ) {
        wpls_create_log_entry( $lead_id, 'system_note', 'Sende-Fehler', 'Keine E-Mail-Vorlage für Typ "' . $template_type . '" gefunden. Sequenz gestoppt.' );
        update_post_meta( $lead_id, '_lead_status', 'stopped' );
        return false;
    }

    // 3. Personalisierung (Spez 5.4)
    $subject = $template['subject'];
    $body    = $template['body'];
    
    $subject = wpls_personalize_template( $subject, $lead_meta );
    $body    = wpls_personalize_template( $body, $lead_meta );

    // 4. E-Mail-Header vorbereiten (Absender aus Einstellungen)
    $options = get_option( 'wpls_settings' );
    $from_name = $options['sender_name'] ?? get_bloginfo( 'name' );
    $from_email = $options['sender_email'] ?? get_bloginfo( 'admin_email' );

    $headers = array();
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    
    // 5. E-Mail via wp_mail() senden
    $sent = wp_mail( $email, $subject, wpautop( $body ), $headers );

    // 6. Loggen
    if ( $sent ) {
        $log_title   = 'E-Mail gesendet: ' . $template_type;
        $log_details = "An: $email, Betreff: $subject";
        wpls_create_log_entry( $lead_id, 'email_sent', $log_title, $log_details );
    } else {
        $log_title   = 'Sende-Fehler (wp_mail): ' . $template_type;
        $log_details = 'Die E-Mail konnte nicht über wp_mail() gesendet werden. Prüfen Sie Ihr SMTP-Plugin.';
        wpls_create_log_entry( $lead_id, 'system_note', $log_title, $log_details );
        update_post_meta( $lead_id, '_lead_status', 'stopped' ); // Stoppt die Sequenz bei Sende-Fehler
    }

    return $sent;
}

/**
 * Hilfsfunktion: Findet eine E-Mail-Vorlage anhand ihres Typs.
 *
 * @param string $template_type Der Meta-Wert von '_template_type'.
 * @return array|null Ein Array mit 'subject' und 'body' oder null.
 */
function wpls_get_template_by_type( $template_type ) {
    $args = array(
        'post_type'      => 'email_template',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => '_template_type',
                'value'   => $template_type,
                'compare' => '=',
            ),
        ),
    );
    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        $post = $query->posts[0];
        $subject = get_post_meta( $post->ID, '_template_email_subject', true );
        return array(
            'subject' => $subject,
            'body'    => $post->post_content,
        );
    }
    
    return null;
}

/**
 * Hilfsfunktion: Ersetzt Platzhalter in einem Template.
 */
function wpls_personalize_template( $content, $lead_meta ) {
    // Stellt sicher, dass wir Array-Werte korrekt abfangen (obwohl get_post_meta oft verschachtelt ist)
    $meta = array_map( function($a) { return $a[0] ?? ''; }, $lead_meta );

    $placeholders = array(
        '[FIRST_NAME]' => $meta['_lead_first_name'] ?? '',
        '[LAST_NAME]'  => $meta['_lead_last_name'] ?? '',
        '[EMAIL]'      => $meta['_lead_contact_email'] ?? '',
        '[COMPANY]'    => $meta['_lead_company_name'] ?? '',
        '[ROLE]'       => $meta['_lead_role'] ?? '',
    );

    return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
}
?>