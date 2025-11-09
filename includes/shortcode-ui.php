<?php
/**
 * Registriert den Frontend-Shortcode [lead_manager_ui] und lädt die Assets (Spez 4.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Registriert den Shortcode.
 */
function wpls_register_frontend_shortcode() {
    add_shortcode( 'lead_manager_ui', 'wpls_render_frontend_ui' );
}
add_action( 'init', 'wpls_register_frontend_shortcode' );

/**
 * Lädt die CSS- und JS-Dateien für das Frontend, falls der Shortcode verwendet wird.
 * (Wir laden es hier nur, wenn der Shortcode auf der Seite ist)
 */
function wpls_enqueue_frontend_scripts() {
    // Sicherstellen, dass wir nicht im Admin-Bereich sind
    if ( is_admin() ) {
        return;
    }

    global $post;
    // Nur laden, wenn der Shortcode auf der Seite vorhanden ist
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'lead_manager_ui' ) ) {
        
        // Pfad zur JS-Datei
        $js_file_path = plugin_dir_url( __FILE__ ) . '../assets/js/frontend-manager.js';
        $js_version = filemtime( plugin_dir_path( __FILE__ ) . '../assets/js/frontend-manager.js' );

        wp_enqueue_script(
            'wpls-frontend-manager',
            $js_file_path,
            array(), // Keine Abhängigkeiten
            $js_version,
            true // Im Footer laden
        );

        // Daten an das JS übergeben (Spez 4.1)
        wp_localize_script(
            'wpls-frontend-manager',
            'wpls_frontend_data',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'security_nonce' => wp_create_nonce( 'wpls_ajax_nonce' ),
                'leads'    => wpls_get_frontend_leads_data() // Lade die Leads initial (Spez 4.1)
            )
        );
        
        // Fügen wir einfaches CSS hinzu, damit es gut aussieht
        wpls_output_frontend_css();
    }
}
add_action( 'wp_enqueue_scripts', 'wpls_enqueue_frontend_scripts' );

/**
 * Holt die Lead-Daten für das Frontend (Spez 4.1)
 */
function wpls_get_frontend_leads_data() {
    // Nur Admins/berechtigte Benutzer sollten dies sehen
    if ( ! current_user_can( 'manage_options' ) ) {
        return array();
    }

    $leads_data = array();
    $args = array(
        'post_type'      => 'lead',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $lead_id = get_the_ID();
            $meta = get_post_meta( $lead_id );
            
            $leads_data[] = array(
                'id'         => $lead_id,
                'name'       => get_the_title(),
                'email'      => $meta['_lead_contact_email'][0] ?? '',
                'company'    => $meta['_lead_company_name'][0] ?? '',
                'status'     => $meta['_lead_status'][0] ?? 'new',
                'sent_count' => (int) ( $meta['_lead_follow_ups_sent'][0] ?? 0 ),
                'showed_call' => $meta['_lead_showed_call'][0] ?? ''
            );
        }
    }
    wp_reset_postdata();
    return $leads_data;
}


/**
 * Rendert das HTML für den Shortcode [lead_manager_ui] (Spez 4.0)
 */
function wpls_render_frontend_ui() {
    // Dieser Shortcode ist nur für angemeldete Admins/Manager
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p>' . __( 'Sie haben keine Berechtigung, diese Ansicht zu sehen.', 'wp-lead-sequencer' ) . '</p>';
    }

    // Puffer starten
    ob_start();
    ?>
    <div id="wpls-frontend-manager">
        
        <!-- Bereich 1: Neuer Lead (Formular) (Spez 4.1 Aktion 1) -->
        <div class="wpls-section" id="wpls-new-lead-section">
            <h2><?php _e( 'Neuen Lead schnell hinzufügen', 'wp-lead-sequencer' ); ?></h2>
            <form id="wpls-new-lead-form">
                <div class="wpls-form-grid">
                    <input type="text" id="wpls_first_name" placeholder="<?php _e( 'Vorname', 'wp-lead-sequencer' ); ?>">
                    <input type="text" id="wpls_last_name" placeholder="<?php _e( 'Nachname', 'wp-lead-sequencer' ); ?>">
                    <input type="email" id="wpls_contact_email" placeholder="<?php _e( 'E-Mail (Erforderlich)', 'wp-lead-sequencer' ); ?>" required>
                    <input type="text" id="wpls_company_name" placeholder="<?php _e( 'Firma', 'wp-lead-sequencer' ); ?>">
                    <input type="text" id="wpls_role" placeholder="<?php _e( 'Rolle/Position', 'wp-lead-sequencer' ); ?>">
                </div>
                <button type="submit" id="wpls-add-lead-submit"><?php _e( 'Lead hinzufügen', 'wp-lead-sequencer' ); ?></button>
                <div id="wpls-form-message" class="wpls-message" style="display: none;"></div>
            </form>
        </div>

        <!-- Bereich 2: Lead-Übersichtstabelle -->
        <div class="wpls-section">
            <h2><?php _e( 'Lead-Übersicht', 'wp-lead-sequencer' ); ?></h2>
            <table id="wpls-lead-table">
                <thead>
                    <tr>
                        <th><?php _e( 'Name', 'wp-lead-sequencer' ); ?></th>
                        <th><?php _e( 'E-Mail', 'wp-lead-sequencer' ); ?></th>
                        <th><?php _e( 'Firma', 'wp-lead-sequencer' ); ?></th>
                        <th><?php _e( 'Status', 'wp-lead-sequencer' ); ?></th>
                        <th><?php _e( 'Gesendet', 'wp-lead-sequencer' ); ?></th>
                        <th><?php _e( 'Aktionen', 'wp-lead-sequencer' ); ?></th>
                    </tr>
                </thead>
                <tbody id="wpls-lead-table-body">
                    <!-- Wird dynamisch per JS (aus wpls_frontend_data.leads) befüllt -->
                </tbody>
            </table>
        </div>
    </div>
    <?php
    
    // Puffer zurückgeben
    return ob_get_clean();
}

/**
 * Fügt das Inline-CSS für das Frontend hinzu
 */
function wpls_output_frontend_css() {
    ?>
    <style>
        #wpls-frontend-manager {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 1200px;
            margin: 20px auto;
        }
        .wpls-section {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .wpls-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .wpls-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .wpls-form-grid input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        #wpls-add-lead-submit {
            background-color: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }
        #wpls-add-lead-submit:hover {
            background-color: #005a87;
        }
        
        .wpls-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
        }
        .wpls-message.success { background-color: #e0f8e0; border: 1px solid #5cb85c; }
        .wpls-message.error { background-color: #f8e0e0; border: 1px solid #d9534f; }
        
        #wpls-lead-table {
            width: 100%;
            border-collapse: collapse;
        }
        #wpls-lead-table th, #wpls-lead-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        #wpls-lead-table th {
            background-color: #f9f9f9;
        }
        .wpls-status-label {
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
        }
        .wpls-status-new { background-color: #e0e0e0; }
        .wpls-status-sequencing { background-color: #cce5ff; color: #004085; }
        .wpls-status-booked { background-color: #d4edda; color: #155724; }
        .wpls-status-stopped { background-color: #f8d7da; color: #721c24; }
        
        .wpls-action-btn {
            background: #f6f6f6;
            border: 1px solid #ccc;
            color: #555;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }
        .wpls-action-btn.start {
            background-color: #5cb85c;
            border-color: #4cae4c;
            color: white;
        }
        .wpls-action-btn.noshow {
            background-color: #d9534f;
            border-color: #d43f3a;
            color: white;
        }
        .wpls-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
    <?php
}
?>