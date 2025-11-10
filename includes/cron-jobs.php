<?php
/**
 * Registriert und verwaltet alle WP-Cron-Jobs für die Automatisierung.
 * (Aktualisiert auf "Stunden" statt "Tage")
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// --- CRON SCHEDULING (Aktivierung / Deaktivierung) ---

/**
 * Plant die Cron-Jobs bei der Aktivierung.
 */
function wpls_schedule_cron_jobs() {
    // 1. Cron-Job für Follow-up-Prüfung (Spez 5.2)
    if ( ! wp_next_scheduled( 'wpls_cron_job_follow_up_check' ) ) {
        // Plant den Job, 'wpls_15_minutes' (unten definiert) zu verwenden.
        wp_schedule_event( time(), 'wpls_15_minutes', 'wpls_cron_job_follow_up_check' );
    }
    
    // 2. Cron-Job für No-Show-Prüfung (Spez 5.3)
    if ( ! wp_next_scheduled( 'wpls_cron_job_noshow_check' ) ) {
        // Plant den Job alle 30 Minuten (Standard-WP-Intervall)
        wp_schedule_event( time(), 'thirty_minutes', 'wpls_cron_job_noshow_check' );
    }
}
// register_activation_hook( WPLS_PLUGIN_DIR . 'wp-lead-sequencer.php', 'wpls_schedule_cron_jobs' );
// (Hinweis: Der Hook ist bereits in der Haupt-Plugin-Datei)


/**
 * Entfernt die Cron-Jobs bei der Deaktivierung.
 */
function wpls_unschedule_cron_jobs() {
    wp_clear_scheduled_hook( 'wpls_cron_job_follow_up_check' );
    wp_clear_scheduled_hook( 'wpls_cron_job_noshow_check' );
}
// register_deactivation_hook( WPLS_PLUGIN_DIR . 'wp-lead-sequencer.php', 'wpls_unschedule_cron_jobs' );
// (Hinweis: Der Hook ist bereits in der Haupt-Plugin-Datei)


// --- CRON INTERVALL HINZUFÜGEN ---

/**
 * Fügt ein benutzerdefiniertes 15-Minuten-Intervall zu WP-Cron hinzu (Spez 5.2)
 */
function wpls_add_cron_intervals( $schedules ) {
    $schedules['wpls_15_minutes'] = array(
        'interval' => 900, // 15 * 60 Sekunden
        'display'  => __( 'Alle 15 Minuten', 'wp-lead-sequencer' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'wpls_add_cron_intervals' );


// --- CRON JOB AKTIONEN ---

/**
 * Registriert die Funktionen, die von den Cron-Jobs ausgeführt werden.
 */
add_action( 'wpls_cron_job_follow_up_check', 'wpls_execute_follow_up_check' );
add_action( 'wpls_cron_job_noshow_check', 'wpls_execute_noshow_check' );


/**
 * JOB 1: Führt die Follow-up-Prüfung durch (Spez 5.2)
 * Wird alle 15 Minuten ausgeführt.
 * (Aktualisiert auf "Stunden" statt "Tage")
 */
function wpls_execute_follow_up_check() {
    
    // 1. Einstellungen abrufen
    $options = get_option( 'wpls_settings' );
    $max_follow_ups = (int) ( $options['max_follow_ups'] ?? 5 ); // Standard auf 5
    $hours_between   = (int) ( $options['hours_between_follow_ups'] ?? 24 ); // Standard auf 24h
    
    if ( $max_follow_ups <= 0 || $hours_between <= 0 ) {
        return; // Automatisierung ist in den Einstellungen deaktiviert
    }
    
    // --- Logik von Tagen auf Stunden geändert ---
    $hours_in_seconds = $hours_between * HOUR_IN_SECONDS; // HOUR_IN_SECONDS = 3600
    $current_time     = time();

    // 2. Leads holen (Spez 5.2 Kriterien)
    $args = array(
        'post_type'      => 'lead',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_lead_status',
                'value'   => 'sequencing',
                'compare' => '=',
            ),
            array(
                'key'     => '_lead_call_scheduled',
                'value'   => '1',
                'compare' => '!=', // Nicht senden, wenn ein Call gebucht ist
            ),
        ),
    );
    
    $leads_query = new WP_Query( $args );

    // 3. Leads durchlaufen
    if ( $leads_query->have_posts() ) {
        while ( $leads_query->have_posts() ) {
            $leads_query->the_post();
            $lead_id = get_the_ID();
            
            $sent_count = (int) get_post_meta( $lead_id, '_lead_follow_ups_sent', true );
            $last_date  = (int) get_post_meta( $lead_id, '_lead_sequence_last_date', true );

            // 4. Logik prüfen (Spez 5.2)
            if ( $sent_count < $max_follow_ups ) {
                
                // Prüfen, ob genügend Zeit vergangen ist (in STUNDEN)
                if ( $last_date <= ( $current_time - $hours_in_seconds ) ) {
                    
                    // Nächste E-Mail senden
                    $next_email_num = $sent_count + 1;
                    $template_type  = 'follow_up_' . $next_email_num;
                    
                    $sent = wpls_send_email( $lead_id, $template_type );
                    
                    if ( $sent ) {
                        // Meta aktualisieren
                        update_post_meta( $lead_id, '_lead_follow_ups_sent', $next_email_num );
                        update_post_meta( $lead_id, '_lead_sequence_last_date', $current_time );
                        
                        // Loggen (wpls_send_email loggt bereits den Versand, wir loggen die Zählung)
                        wpls_create_log_entry( $lead_id, 'system_note', 'Follow-up ' . $next_email_num . ' gesendet', 'Automatisch durch WP-Cron.' );
                    } else {
                        // Versand fehlgeschlagen (z.B. Vorlage nicht gefunden)
                        // wpls_send_email() erstellt bereits einen Log und stoppt die Sequenz.
                    }
                }
            } else {
                // Maximale Anzahl erreicht. Sequenz für diesen Lead stoppen.
                update_post_meta( $lead_id, '_lead_status', 'stopped' );
                wpls_create_log_entry( $lead_id, 'system_note', 'Sequenz gestoppt', 'Maximale Anzahl an Follow-ups erreicht.' );
            }
        }
    }
    wp_reset_postdata();
}


/**
 * JOB 2: Führt die No-Show-Prüfung durch (Spez 5.3)
 * Wird alle 30 Minuten ausgeführt.
 */
function wpls_execute_noshow_check() {
    
    // 1. Leads holen (Spez 5.3 Kriterien)
    $args = array(
        'post_type'      => 'lead',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_lead_showed_call',
                'value'   => 'no', // Wurde als No-Show markiert
                'compare' => '=',
            ),
        ),
    );
    
    $leads_query = new WP_Query( $args );

    // 2. Leads durchlaufen
    if ( $leads_query->have_posts() ) {
        while ( $leads_query->have_posts() ) {
            $leads_query->the_post();
            $lead_id = get_the_ID();
            
            // 3. No-Show E-Mail senden
            $sent = wpls_send_email( $lead_id, 'no_show' );
            
            if ( $sent ) {
                // 4. Status aktualisieren, um Doppel-Versand zu verhindern
                update_post_meta( $lead_id, '_lead_showed_call', 'followed_up' );
                wpls_create_log_entry( $lead_id, 'email_sent', 'No-Show E-Mail gesendet', 'Automatisch durch WP-Cron.' );
            }
        }
    }
    wp_reset_postdata();
}
?>