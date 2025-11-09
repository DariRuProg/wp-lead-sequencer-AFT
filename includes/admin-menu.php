<?php
/**
 * Erstellt die Admin-Menüstruktur für das Plugin.
 * (Neu generiert, um [redacted] Fehler zu beheben)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Registriert das Hauptmenü und die Untermenüs.
 */
function wpls_register_admin_menu() {
    
    // Hauptmenü-Eintrag "Lead Sequencer" (Spez 3.0)
    add_menu_page(
        __( 'Lead Sequencer', 'wp-lead-sequencer' ),
        __( 'Lead Sequencer', 'wp-lead-sequencer' ),
        'manage_options', // Fähigkeit (Capability)
        'wpls-main-crm', // Slug
        'wpls_crm_page_html', // Callback-Funktion für die Hauptseite
        'dashicons-email-alt2', // Icon
        25 // Position
    );

    // Untermenü: Übersicht (CRM) (Spez 3.1)
    // Zeigt auf die Hauptseite (wpls-main-crm)
    add_submenu_page(
        'wpls-main-crm', // Parent-Slug
        __( 'Übersicht (CRM)', 'wp-lead-sequencer' ),
        __( 'Übersicht (CRM)', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-main-crm', // Selber Slug wie Hauptmenü
        'wpls_crm_page_html' // Selber Callback
    );

    // Untermenü: E-Mail-Vorlagen (Spez 3.2)
    // Zeigt auf die Standard-Admin-Seite für den 'email_template' CPT
    add_submenu_page(
        'wpls-main-crm',
        __( 'E-Mail-Vorlagen', 'wp-lead-sequencer' ),
        __( 'E-Mail-Vorlagen', 'wp-lead-sequencer' ),
        'manage_options',
        'edit.php?post_type=email_template' // Link zur CPT-Übersicht
    );
    
    // Untermenü: Neue Vorlage (versteckt, aber nützlich für Kontext)
    add_submenu_page(
        null, // Kein Elternteil (versteckt im Menü)
        __( 'Neue Vorlage', 'wp-lead-sequencer' ),
        __( 'Neue Vorlage', 'wp-lead-sequencer' ),
        'manage_options',
        'post-new.php?post_type=email_template'
    );

    // Untermenü: Lead-Import (Spez 3.3)
    add_submenu_page(
        'wpls-main-crm',
        __( 'Lead-Import', 'wp-lead-sequencer' ),
        __( 'Lead-Import', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-import', // Eigener Slug
        'wpls_import_page_display' // Callback aus import-export.php
    );

    // Untermenü: Lead-Export (Spez 3.4)
    add_submenu_page(
        'wpls-main-crm',
        __( 'Lead-Export', 'wp-lead-sequencer' ),
        __( 'Lead-Export', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-export', // Eigener Slug
        'wpls_export_page_display' // Callback aus import-export.php
    );

    // Untermenü: Statistiken / Logs (Spez 3.5)
    // Zeigt auf die Standard-Admin-Seite für den 'lead_log' CPT
    add_submenu_page(
        'wpls-main-crm',
        __( 'Statistiken / Logs', 'wp-lead-sequencer' ),
        __( 'Statistiken / Logs', 'wp-lead-sequencer' ),
        'manage_options',
        'edit.php?post_type=lead_log'
    );

    // Untermenü: Einstellungen (Spez 3.6)
    add_submenu_page(
        'wpls-main-crm',
        __( 'Einstellungen', 'wp-lead-sequencer' ),
        __( 'Einstellungen', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-settings', // Eigener Slug
        'wpls_settings_page_html' // Callback aus settings-api.php
    );
}
add_action( 'admin_menu', 'wpls_register_admin_menu' );


/**
 * Callback für die Haupt-CRM-Seite (Spez 3.1)
 * Zeigt die WP_List_Table an.
 * (Aktualisiert mit Such-Formular - Aufgabe 4)
 */
function wpls_crm_page_html() {
    
    // Erstellt die Instanz unserer eigenen Tabellen-Klasse
    $leads_list_table = new WPLS_Leads_List_Table();
    // Holt die Daten und bereitet die Tabelle vor
    $leads_list_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e( 'Lead-Übersicht (CRM)', 'wp-lead-sequencer' ); ?></h1>
        
        <a href="<?php echo admin_url( 'post-new.php?post_type=lead' ); ?>" class="page-title-action">
            <?php _e( 'Neuen Lead hinzufügen', 'wp-lead-sequencer' ); ?>
        </a>

        <?php
        // Zeigt Admin-Notizen an (Logik aus Originaldatei beibehalten)
        if ( isset( $_GET['message'] ) && $_GET['message'] === 'sequence_started' ) {
            $count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
            echo '<div id="message" class="updated notice is-dismissible"><p>' . 
                sprintf( _n( 'Sequenz für %d Lead gestartet.', 'Sequenz für %d Leads gestartet.', $count, 'wp-lead-sequencer' ), $count ) . 
            '</p></div>';
        }
        if ( isset( $_GET['message'] ) && $_GET['message'] === 'deleted' ) {
            $count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
            echo '<div id="message" class="updated notice is-dismissible"><p>' . 
                sprintf( _n( '%d Lead gelöscht.', '%d Leads gelöscht.', $count, 'wp-lead-sequencer' ), $count ) . 
            '</p></div>';
        }
        ?>

        <!-- NEU: Suchformular (Aufgabe 4) -->
        <form method="get">
            <!-- 'page' ist wichtig, damit die Suche auf der CRM-Seite bleibt -->
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            // Suchfeld anzeigen
            $leads_list_table->search_box( __('Leads suchen', 'wp-lead-sequencer'), 'lead_search_id' );
            ?>
        </form>
        
        <!-- Haupt-Formular für Bulk Actions und Filter -->
        <form id="leads-filter" method="get">
            <!-- Wichtige hidden fields für WP_List_Table -->
            <input type="hidden" name="post_type" value="lead" /> <!-- Aus Originaldatei beibehalten -->
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            
            <?php
            // Filter (Status) und Bulk Actions anzeigen
            $leads_list_table->display();
            ?>
        </form>
    </div>
    <?php
}
?>