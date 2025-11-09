<?php
/**
 * Plugin Name:       WP Lead Sequencer
 * Plugin URI:        https://example.com/
 * Description:       Verwaltet Leads und automatisiert E-Mail-Follow-up-Sequenzen.
 * Version:           1.0.0
 * Author:            Dein Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-lead-sequencer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Plugin-Konstanten
define( 'WPLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// --- 1. Post Types & Metaboxen ---
require_once WPLS_PLUGIN_DIR . 'includes/post-types.php';

// --- 2. Admin-Menü & Seiten ---
require_once WPLS_PLUGIN_DIR . 'includes/admin-menu.php';

// --- 3. Einstellungs-API ---
require_once WPLS_PLUGIN_DIR . 'includes/settings-api.php';

// --- 4. Import & Export ---
require_once WPLS_PLUGIN_DIR . 'includes/import-export.php';

// --- 5. Kernlogik (Senden, Logging) ---
require_once WPLS_PLUGIN_DIR . 'includes/core-logic.php';

// --- 6. AJAX-Handler (Sequenz starten etc.) ---
require_once WPLS_PLUGIN_DIR . 'includes/ajax-handlers.php';

// --- 7. Cron-Jobs (Automatisierung) ---
require_once WPLS_PLUGIN_DIR . 'includes/cron-jobs.php';

// --- 8. WP_List_Table (CRM-Ansicht) ---
require_once WPLS_PLUGIN_DIR . 'includes/class-leads-list-table.php';

// --- 9. Frontend Shortcode UI ---
require_once WPLS_PLUGIN_DIR . 'includes/shortcode-ui.php';

// --- 10. REST API Endpunkte ---
require_once WPLS_PLUGIN_DIR . 'includes/rest-api.php';


/**
 * Wird bei der Plugin-Aktivierung ausgeführt.
 */
function wpls_plugin_activation() {
    // CPTs registrieren, damit Flush Rewrite Rules funktioniert
    wpls_register_post_types();
    
    // Cron-Jobs planen (aus cron-jobs.php)
    wpls_schedule_cron_jobs();
    
    // Wichtig für CPTs und REST-API-Endpunkte
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpls_plugin_activation' );

/**
 * Wird bei der Plugin-Deaktivierung ausgeführt.
 */
function wpls_plugin_deactivation() {
    // Cron-Jobs entfernen (aus cron-jobs.php)
    wpls_unschedule_cron_jobs();
    
    // Rewrite-Regeln neu laden
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpls_plugin_deactivation' );

/**
 * Lädt Admin-spezifische Stylesheets (z.B. für die WP_List_Table).
 * (Aufgabe 1)
 */
function wpls_enqueue_admin_styles( $hook_suffix ) {
    // Nur auf unseren Plugin-Seiten laden
    // Prüft, ob der Hook 'wpls-' enthält (für Unterseiten) ODER die Hauptseite ist ODER eine Beitragsseite ist
    if ( strpos( $hook_suffix, 'wpls-' ) === false && $hook_suffix !== 'toplevel_page_wpls-main-crm' && $hook_suffix !== 'post-new.php' && $hook_suffix !== 'post.php' ) {
        return;
    }

    $current_post_type = get_post_type();

    // Speziell für die CRM-Seite und E-Mail-Vorlagen (oder Lead-Bearbeitung)
    if ( $hook_suffix === 'toplevel_page_wpls-main-crm' || $current_post_type === 'email_template' || $current_post_type === 'lead' ) {
        
        $css_file_path_url = plugin_dir_url( __FILE__ ) . 'assets/css/admin-styles.css';
        $css_file_path_dir = plugin_dir_path( __FILE__ ) . 'assets/css/admin-styles.css';

        // Sicherstellen, dass die Datei existiert
        if ( file_exists( $css_file_path_dir ) ) {
            $css_version = filemtime( $css_file_path_dir );
            
            wp_enqueue_style(
                'wpls-admin-styles',
                $css_file_path_url,
                array(),
                $css_version
            );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'wpls_enqueue_admin_styles' );
?>