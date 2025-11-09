<?php
/**
 * Plugin Name:       WP Lead Sequencer
 * Description:       Verwaltet Leads und automatisiert E-Mail-Follow-up-Sequenzen.
 * Version:           1.0.0
 * Author:            Utilflow.com
 * Text Domain:       wp-lead-sequencer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'WPLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Aktivierungs-Hook
 * Registriert CPTs und leert die Rewrite-Regeln, damit die CPTs gefunden werden.
 */
function wpls_activate() {
    wpls_register_post_types();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpls_activate' );

/**
 * Deaktivierungs-Hook
 * Leert die Rewrite-Regeln.
 */
function wpls_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpls_deactivate' );

/**
 * Registriert die Custom Post Types für das Plugin.
 */
function wpls_register_post_types() {

    // CPT: lead (Leads)
    $lead_labels = array(
        'name'                  => _x( 'Leads', 'Post Type General Name', 'wp-lead-sequencer' ),
        'singular_name'         => _x( 'Lead', 'Post Type Singular Name', 'wp-lead-sequencer' ),
        'menu_name'             => __( 'Leads', 'wp-lead-sequencer' ),
        'all_items'             => __( 'Alle Leads', 'wp-lead-sequencer' ),
        'add_new_item'          => __( 'Neuen Lead hinzufügen', 'wp-lead-sequencer' ),
        'edit_item'             => __( 'Lead bearbeiten', 'wp-lead-sequencer' ),
    );
    $lead_args = array(
        'label'                 => __( 'Lead', 'wp-lead-sequencer' ),
        'labels'                => $lead_labels,
        'supports'              => array( 'title' ), // Titel wird programmatisch befüllt
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false, // Wird dem eigenen Menü hinzugefügt
        'capability_type'       => 'post',
        'hierarchical'          => false,
        'rewrite'               => false,
        'show_in_rest'          => true, // Für die REST API
    );
    register_post_type( 'lead', $lead_args );

    // CPT: email_template (E-Mail-Vorlagen)
    $template_labels = array(
        'name'                  => _x( 'E-Mail-Vorlagen', 'Post Type General Name', 'wp-lead-sequencer' ),
        'singular_name'         => _x( 'E-Mail-Vorlage', 'Post Type Singular Name', 'wp-lead-sequencer' ),
        'menu_name'             => __( 'E-Mail-Vorlagen', 'wp-lead-sequencer' ),
        'all_items'             => __( 'Alle Vorlagen', 'wp-lead-sequencer' ),
        'add_new_item'          => __( 'Neue Vorlage hinzufügen', 'wp-lead-sequencer' ),
        'edit_item'             => __( 'Vorlage bearbeiten', 'wp-lead-sequencer' ),
    );
    $template_args = array(
        'label'                 => __( 'E-Mail-Vorlage', 'wp-lead-sequencer' ),
        'labels'                => $template_labels,
        'supports'              => array( 'title', 'editor' ), // Titel und Inhalt (Body)
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false, // Wird dem eigenen Menü hinzugefügt
        'capability_type'       => 'post',
        'hierarchical'          => false,
        'rewrite'               => false,
    );
    register_post_type( 'email_template', $template_args );

    // CPT: lead_log (Logbuch)
    $log_labels = array(
        'name'                  => _x( 'Logs', 'Post Type General Name', 'wp-lead-sequencer' ),
        'singular_name'         => _x( 'Log-Eintrag', 'Post Type Singular Name', 'wp-lead-sequencer' ),
        'menu_name'             => __( 'Logs', 'wp-lead-sequencer' ),
    );
    $log_args = array(
        'label'                 => __( 'Log-Eintrag', 'wp-lead-sequencer' ),
        'labels'                => $log_labels,
        'supports'              => array( 'title', 'editor' ), // Titel (Aktion) und Inhalt (Details)
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false, // Wird dem eigenen Menü hinzugefügt
        'capability_type'       => 'post',
        'hierarchical'          => false,
        'rewrite'               => false,
    );
    register_post_type( 'lead_log', $log_args );

}
add_action( 'init', 'wpls_register_post_types' );

/**
 * Erstellt das Hauptmenü im Admin-Bereich.
 */
function wpls_add_admin_menu() {
    // Hauptmenü-Seite "Lead Sequencer"
    add_menu_page(
        __( 'Lead Sequencer', 'wp-lead-sequencer' ), // Page Title
        __( 'Lead Sequencer', 'wp-lead-sequencer' ), // Menu Title
        'manage_options', // Capability
        'wpls-main-menu', // Menu Slug
        'wpls_overview_page_html', // Callback für die Hauptseite (Übersicht)
        'dashicons-email-alt2', // Icon
        25 // Position
    );

    // Untermenü: Übersicht (verweist auf die Lead CPT-Liste)
    add_submenu_page(
        'wpls-main-menu', // Parent Slug
        __( 'Übersicht (CRM)', 'wp-lead-sequencer' ), // Page Title
        __( 'Übersicht (CRM)', 'wp-lead-sequencer' ), // Menu Title
        'manage_options', // Capability
        'edit.php?post_type=lead' // Menu Slug (verweist direkt auf CPT)
    );
    
    // Untermenü: E-Mail-Vorlagen (verweist auf CPT-Liste)
    add_submenu_page(
        'wpls-main-menu',
        __( 'E-Mail-Vorlagen', 'wp-lead-sequencer' ),
        __( 'E-Mail-Vorlagen', 'wp-lead-sequencer' ),
        'manage_options',
        'edit.php?post_type=email_template'
    );

    // Untermenü: Lead-Import
    add_submenu_page(
        'wpls-main-menu',
        __( 'Lead-Import', 'wp-lead-sequencer' ),
        __( 'Lead-Import', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-import',
        'wpls_import_page_html'
    );

    // Untermenü: Lead-Export
    add_submenu_page(
        'wpls-main-menu',
        __( 'Lead-Export', 'wp-lead-sequencer' ),
        __( 'Lead-Export', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-export',
        'wpls_export_page_html'
    );

    // Untermenü: Statistiken / Logs (verweist auf CPT-Liste)
    add_submenu_page(
        'wpls-main-menu',
        __( 'Statistiken / Logs', 'wp-lead-sequencer' ),
        __( 'Statistiken / Logs', 'wp-lead-sequencer' ),
        'manage_options',
        'edit.php?post_type=lead_log'
    );

    // Untermenü: Einstellungen
    add_submenu_page(
        'wpls-main-menu',
        __( 'Einstellungen', 'wp-lead-sequencer' ),
        __( 'Einstellungen', 'wp-lead-sequencer' ),
        'manage_options',
        'wpls-settings',
        'wpls_settings_page_html' // Funktion für Einstellungsseite
    );
    
    // Entfernt die automatisch erstellte erste Untermenü-Seite (die ein Duplikat ist)
    remove_submenu_page( 'wpls-main-menu', 'wpls-main-menu' );
}
add_action( 'admin_menu', 'wpls_add_admin_menu' );


// --- Platzhalter-Funktionen für die Admin-Seiten ---

function wpls_overview_page_html() {
    // Diese Funktion wird benötigt, weil sie der Callback für die Hauptseite ist.
    // Wir leiten aber direkt zur CPT-Liste weiter. 
    // Alternativ können wir die 'Übersicht' zur Hauptseite machen.
    // Für jetzt:
    echo '<h1>Übersicht</h1><p>Die Haupt-CRM-Ansicht (WP_List_Table) für Leads wird hier implementiert.</p>';
    
    // Bessere Lösung (ersetzt 'wpls_overview_page_html' im add_menu_page call):
    // 'edit.php?post_type=lead'
    // Und die erste add_submenu_page wird zur Hauptseite.
    
    // Ich lasse es vorerst so, bis wir die WP_List_Table für Leads (Punkt 3.1) bauen.
}

function wpls_import_page_html() {
    echo '<h1>Lead-Import (CSV)</h1><p>Hier kommt das Upload-Formular und die Mapping-Logik (Anforderung #1).</p>';
}

function wpls_export_page_html() {
    echo '<h1>Lead-Export (CSV)</h1><p>Hier kommt der Download-Button (Anforderung #3).</p>';
}

// --- Einstellungsseite (Settings API) --- (Spez 3.6)

/**
 * HTML für die Einstellungsseite (Callback von add_submenu_page)
 */
function wpls_settings_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php
        // Zeigt die Tabs an
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="?post_type=lead&page=wpls-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('Allgemein', 'wp-lead-sequencer'); ?></a>
            <a href="?post_type=lead&page=wpls-settings&tab=api_keys" class="nav-tab <?php echo $active_tab == 'api_keys' ? 'nav-tab-active' : ''; ?>"><?php _e('API-Keys', 'wp-lead-sequencer'); ?></a>
            <a href="?post_type=lead&page=wpls-settings&tab=sequence" class="nav-tab <?php echo $active_tab == 'sequence' ? 'nav-tab-active' : ''; ?>"><?php _e('Sequenz-Logik', 'wp-lead-sequencer'); ?></a>
        </h2>

        <form action="options.php" method="post">
            <?php
            // Lädt die Felder basierend auf dem aktiven Tab
            if ( $active_tab == 'general' ) {
                settings_fields( 'wpls_settings_general' );
                do_settings_sections( 'wpls_settings_general' );
            } elseif ( $active_tab == 'api_keys' ) {
                settings_fields( 'wpls_settings_api' );
                do_settings_sections( 'wpls_settings_api' );
            } elseif ( $active_tab == 'sequence' ) {
                settings_fields( 'wpls_settings_sequence' );
                do_settings_sections( 'wpls_settings_sequence' );
            }
            
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Registriert die Einstellungen mit der Settings API.
 */
function wpls_register_settings() {

    // Optionsgruppe 'wpls_settings' für alle unsere Einstellungen
    // Wir verwenden unterschiedliche Gruppen für unterschiedliche Tabs
    
    // --- Tab 1: Allgemein ---
    register_setting( 'wpls_settings_general', 'wpls_settings', 'wpls_settings_validate' );

    add_settings_section(
        'wpls_section_general',
        __( 'Allgemeine E-Mail-Einstellungen', 'wp-lead-sequencer' ),
        '__return_false', // Kein Callback für Sektionsbeschreibung
        'wpls_settings_general'
    );

    add_settings_field(
        'sender_name',
        __( 'Absender-Name', 'wp-lead-sequencer' ),
        'wpls_field_text_callback',
        'wpls_settings_general',
        'wpls_section_general',
        ['id' => 'sender_name', 'label' => 'Name, der im "Von"-Feld angezeigt wird.']
    );

    add_settings_field(
        'sender_email',
        __( 'Absender-E-Mail', 'wp-lead-sequencer' ),
        'wpls_field_text_callback',
        'wpls_settings_general',
        'wpls_section_general',
        ['id' => 'sender_email', 'type' => 'email', 'label' => 'E-Mail-Adresse, von der gesendet wird.']
    );

    // --- Tab 2: API-Keys ---
    register_setting( 'wpls_settings_api', 'wpls_settings', 'wpls_settings_validate' );

    add_settings_section(
        'wpls_section_api_google',
        __( 'Google / Gmail API', 'wp-lead-sequencer' ),
        '__return_false',
        'wpls_settings_api'
    );
    add_settings_field( 'google_client_id', __( 'Google Client ID', 'wp-lead-sequencer' ), 'wpls_field_text_callback', 'wpls_settings_api', 'wpls_section_api_google', ['id' => 'google_client_id'] );
    add_settings_field( 'google_client_secret', __( 'Google Client Secret', 'wp-lead-sequencer' ), 'wpls_field_text_callback', 'wpls_settings_api', 'wpls_section_api_google', ['id' => 'google_client_secret', 'type' => 'password'] );
    add_settings_field( 'google_refresh_token', __( 'Google Refresh Token', 'wp-lead-sequencer' ), 'wpls_field_text_callback', 'wpls_settings_api', 'wpls_section_api_google', ['id' => 'google_refresh_token', 'type' => 'password'] );

    add_settings_section(
        'wpls_section_api_openrouter',
        __( 'OpenRouter API', 'wp-lead-sequencer' ),
        '__return_false',
        'wpls_settings_api'
    );
    add_settings_field( 'openrouter_api_key', __( 'OpenRouter API Key', 'wp-lead-sequencer' ), 'wpls_field_text_callback', 'wpls_settings_api', 'wpls_section_api_openrouter', ['id' => 'openrouter_api_key', 'type' => 'password'] );

    // --- Tab 3: Sequenz-Logik ---
    register_setting( 'wpls_settings_sequence', 'wpls_settings', 'wpls_settings_validate' );

    add_settings_section(
        'wpls_section_sequence',
        __( 'Automatisierungs-Logik', 'wp-lead-sequencer' ),
        '__return_false',
        'wpls_settings_sequence'
    );

    add_settings_field(
        'max_follow_ups',
        __( 'Maximale Anzahl Follow-ups', 'wp-lead-sequencer' ),
        'wpls_field_text_callback',
        'wpls_settings_sequence',
        'wpls_section_sequence',
        ['id' => 'max_follow_ups', 'type' => 'number', 'label' => 'Z.B. 3. Sendet follow_up_1, follow_up_2, follow_up_3.']
    );

    add_settings_field(
        'days_between_follow_ups',
        __( 'Tage zwischen Follow-ups', 'wp-lead-sequencer' ),
        'wpls_field_text_callback',
        'wpls_settings_sequence',
        'wpls_section_sequence',
        ['id' => 'days_between_follow_ups', 'type' => 'number', 'label' => 'Tage, die gewartet werden, bevor die nächste E-Mail gesendet wird.']
    );
}
add_action( 'admin_init', 'wpls_register_settings' );

/**
 * Callback-Funktion zum Rendern eines Text-Feldes (wird wiederverwendet).
 */
function wpls_field_text_callback( $args ) {
    $options = get_option( 'wpls_settings' );
    $id = $args['id'];
    $type = isset($args['type']) ? $args['type'] : 'text';
    $value = isset( $options[$id] ) ? $options[$id] : '';
    
    // Bei Passwortfeldern den Wert nicht im HTML anzeigen (außer vielleicht maskiert)
    if ( $type == 'password' && !empty($value) ) {
        $value = '********'; // Platzhalter, da gespeichert
    }

    echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="wpls_settings[' . esc_attr($id) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
    
    if ( isset($args['label']) ) {
        echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
    }
}

/**
 * Validierungs-/Sanitize-Funktion für die Optionen.
 */
function wpls_settings_validate( $input ) {
    $options = get_option( 'wpls_settings' );
    $output = $options ? $options : array(); // Bestehende Optionen laden

    // Felder für Allgemein
    if ( isset( $input['sender_name'] ) ) $output['sender_name'] = sanitize_text_field( $input['sender_name'] );
    if ( isset( $input['sender_email'] ) ) $output['sender_email'] = sanitize_email( $input['sender_email'] );
    
    // Felder für API-Keys (Passwörter nicht ändern, wenn '********' übermittelt wird)
    $api_keys = ['google_client_id', 'google_client_secret', 'google_refresh_token', 'openrouter_api_key'];
    foreach($api_keys as $key) {
        if ( isset($input[$key]) && $input[$key] !== '********' && !empty($input[$key]) ) {
            $output[$key] = sanitize_text_field( $input[$key] );
        }
    }

    // Felder für Sequenz-Logik
    if ( isset( $input['max_follow_ups'] ) ) $output['max_follow_ups'] = intval( $input['max_follow_ups'] );
    if ( isset( $input['days_between_follow_ups'] ) ) $output['days_between_follow_ups'] = intval( $input['days_between_follow_ups'] );

    return $output;
}


// --- METABOXEN ---

/**
 * Registriert die Metaboxen für die CPTs.
 */
function wpls_add_meta_boxes() {
    // Metabox für Leads (Kontaktinfos)
    add_meta_box(
        'wpls_lead_contact_details',
        __( 'Lead: Kontaktinformationen', 'wp-lead-sequencer' ),
        'wpls_lead_contact_metabox_html',
        'lead',
        'normal',
        'high'
    );

    // Metabox für Leads (Firmeninfos)
    add_meta_box(
        'wpls_lead_company_details',
        __( 'Lead: Firmeninformationen', 'wp-lead-sequencer' ),
        'wpls_lead_company_metabox_html',
        'lead',
        'normal',
        'default'
    );
    
    // Metabox für Leads (Tracking-Status)
    add_meta_box(
        'wpls_lead_tracking_status',
        __( 'Lead: Tracking & Status', 'wp-lead-sequencer' ),
        'wpls_lead_tracking_metabox_html',
        'lead',
        'side',
        'high'
    );

    // Metabox für E-Mail-Vorlagen
    add_meta_box(
        'wpls_email_template_details',
        __( 'Vorlagen-Details', 'wp-lead-sequencer' ),
        'wpls_email_template_metabox_html',
        'email_template',
        'normal',
        'high'
    );

    // Metabox für Log-Einträge
    add_meta_box(
        'wpls_lead_log_details',
        __( 'Log-Details', 'wp-lead-sequencer' ),
        'wpls_lead_log_metabox_html',
        'lead_log',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'wpls_add_meta_boxes' );

/**
 * HTML für die Lead-Kontakt-Metabox (Spez 2.1)
 */
function wpls_lead_contact_metabox_html( $post ) {
    wp_nonce_field( 'wpls_lead_save', 'wpls_lead_nonce' );
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e( 'Vorname', 'wp-lead-sequencer' ); ?></th>
            <td><input type="text" name="_lead_first_name" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_first_name', true ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Nachname', 'wp-lead-sequencer' ); ?></th>
            <td><input type="text" name="_lead_last_name" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_last_name', true ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'E-Mail', 'wp-lead-sequencer' ); ?></th>
            <td><input type="email" name="_lead_contact_email" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_contact_email', true ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Telefon', 'wp-lead-sequencer' ); ?></th>
            <td><input type="tel" name="_lead_contact_phone" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_contact_phone', true ) ); ?>" class="regular-text" /></td>
        </tr>
    </table>
    <?php
}

/**
 * HTML für die Lead-Firmen-Metabox (Spez 2.1)
 */
function wpls_lead_company_metabox_html( $post ) {
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e( 'Rolle/Position', 'wp-lead-sequencer' ); ?></th>
            <td><input type="text" name="_lead_role" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_role', true ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Firmenname', 'wp-lead-sequencer' ); ?></th>
            <td><input type="text" name="_lead_company_name" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_company_name', true ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Branche', 'wp-lead-sequencer' ); ?></th>
            <td><input type="text" name="_lead_company_industry" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_company_industry', true ) ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Adresse', 'wp-lead-sequencer' ); ?></th>
            <td><textarea name="_lead_company_address" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, '_lead_company_address', true ) ); ?></textarea></td>
        </tr>
         <tr valign="top">
            <th scope="row"><?php _e( 'Webseite', 'wp-lead-sequencer' ); ?></th>
            <td><input type="url" name="_lead_website" value="<?php echo esc_attr( get_post_meta( $post->ID, '_lead_website', true ) ); ?>" class="regular-text" /></td>
        </tr>
    </table>
    <?php
}

/**
 * HTML für die Lead-Tracking-Metabox (Spez 2.1)
 */
function wpls_lead_tracking_metabox_html( $post ) {
    $status = get_post_meta( $post->ID, '_lead_status', true ) ?: 'new';
    $showed_call = get_post_meta( $post->ID, '_lead_showed_call', true );
    ?>
    <p>
        <strong><?php _e( 'Lead-Status', 'wp-lead-sequencer' ); ?></strong><br>
        <select name="_lead_status">
            <option value="new" <?php selected( $status, 'new' ); ?>><?php _e( 'Neu', 'wp-lead-sequencer' ); ?></option>
            <option value="sequencing" <?php selected( $status, 'sequencing' ); ?>><?php _e( 'In Sequenz', 'wp-lead-sequencer' ); ?></option>
            <option value="booked" <?php selected( $status, 'booked' ); ?>><?php _e( 'Call gebucht', 'wp-lead-sequencer' ); ?></option>
            <option value="stopped" <?php selected( $status, 'stopped' ); ?>><?php _e( 'Gestoppt', 'wp-lead-sequencer' ); ?></option>
        </select>
    </p>
    <p>
        <input type="checkbox" name="_lead_started_sequence" value="1" <?php checked( get_post_meta( $post->ID, '_lead_started_sequence', true ), '1' ); ?> />
        <label for="_lead_started_sequence"><?php _e( 'Sequenz gestartet', 'wp-lead-sequencer' ); ?></label>
    </p>
    <p>
        <input type="checkbox" name="_lead_call_scheduled" value="1" <?php checked( get_post_meta( $post->ID, '_lead_call_scheduled', true ), '1' ); ?> />
        <label for="_lead_call_scheduled"><?php _e( 'Call terminiert', 'wp-lead-sequencer' ); ?></label>
    </p>
    <p>
        <strong><?php _e( 'Call No-Show Status', 'wp-lead-sequencer' ); ?></strong><br>
        <select name="_lead_showed_call">
            <option value="" <?php selected( $showed_call, '' ); ?>><?php _e( 'N/A', 'wp-lead-sequencer' ); ?></option>
            <option value="yes" <?php selected( $showed_call, 'yes' ); ?>><?php _e( 'Ja (erschienen)', 'wp-lead-sequencer' ); ?></option>
            <option value="no" <?php selected( $showed_call, 'no' ); ?>><?php _e( 'Nein (No-Show)', 'wp-lead-sequencer' ); ?></option>
            <option value="followed_up" <?php selected( $showed_call, 'followed_up' ); ?>><?php _e( 'No-Show Follow-up gesendet', 'wp-lead-sequencer' ); ?></option>
        </select>
    </p>
    <hr>
    <p>
        <strong><?php _e( 'Gesendete Follow-ups', 'wp-lead-sequencer' ); ?></strong><br>
        <input type="number" name="_lead_follow_ups_sent" value="<?php echo (int) get_post_meta( $post->ID, '_lead_follow_ups_sent', true ); ?>" class="small-text" />
    </p>
    <p>
        <strong><?php _e( 'Letzte E-Mail (Timestamp)', 'wp-lead-sequencer' ); ?></strong><br>
        <?php 
        $last_date = get_post_meta( $post->ID, '_lead_sequence_last_date', true );
        echo $last_date ? date( 'Y-m-d H:i:s', $last_date ) : 'N/A';
        ?>
        <input type="hidden" name="_lead_sequence_last_date" value="<?php echo esc_attr( $last_date ); ?>" />
    </p>
    <?php
}

/**
 * HTML für die E-Mail-Vorlagen-Metabox (Spez 2.2)
 */
function wpls_email_template_metabox_html( $post ) {
    wp_nonce_field( 'wpls_template_save', 'wpls_template_nonce' );
    $template_type = get_post_meta( $post->ID, '_template_type', true );
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e( 'E-Mail-Betreff', 'wp-lead-sequencer' ); ?></th>
            <td><input type="text" name="_template_email_subject" value="<?php echo esc_attr( get_post_meta( $post->ID, '_template_email_subject', true ) ); ?>" class="large-text" required /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Vorlagen-Typ', 'wp-lead-sequencer' ); ?></th>
            <td>
                <select name="_template_type" required>
                    <option value=""><?php _e( 'Bitte wählen...', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_1" <?php selected( $template_type, 'follow_up_1' ); ?>><?php _e( 'Follow Up 1', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_2" <?php selected( $template_type, 'follow_up_2' ); ?>><?php _e( 'Follow Up 2', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_3" <?php selected( $template_type, 'follow_up_3' ); ?>><?php _e( 'Follow Up 3', 'wp-lead-sequencer' ); ?></option>
                    <option value="no_show" <?php selected( $template_type, 'no_show' ); ?>><?php _e( 'No-Show E-Mail', 'wp-lead-sequencer' ); ?></option>
                </select>
                <p class="description"><?php _e( 'Wichtig für die Automatisierungslogik.', 'wp-lead-sequencer' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * HTML für die Log-Details-Metabox (Spez 2.3)
 */
function wpls_lead_log_metabox_html( $post ) {
    wp_nonce_field( 'wpls_log_save', 'wpls_log_nonce' );
    $log_type = get_post_meta( $post->ID, '_log_type', true );
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e( 'Zugehörige Lead-ID', 'wp-lead-sequencer' ); ?></th>
            <td><input type="number" name="_log_lead_id" value="<?php echo (int) get_post_meta( $post->ID, '_log_lead_id', true ); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Log-Typ', 'wp-lead-sequencer' ); ?></th>
            <td>
                <select name="_log_type">
                    <option value="system_note" <?php selected( $log_type, 'system_note' ); ?>><?php _e( 'System-Notiz', 'wp-lead-sequencer' ); ?></option>
                    <option value="email_sent" <?php selected( $log_type, 'email_sent' ); ?>><?php _e( 'E-Mail gesendet', 'wp-lead-sequencer' ); ?></option>
                    <option value="call_booked" <?php selected( $log_type, 'call_booked' ); ?>><?php _e( 'Call gebucht', 'wp-lead-sequencer' ); ?></option>
                    <option value="sequence_started" <?php selected( $log_type, 'sequence_started' ); ?>><?php _e( 'Sequenz gestartet', 'wp-lead-sequencer' ); ?></option>
                </select>
            </td>
        </tr>
    </table>
    <?php
}


// --- SPEICHERFUNKTIONEN FÜR METABOXEN ---

/**
 * Speichert die Meta-Daten für Leads.
 */
function wpls_save_lead_metabox_data( $post_id ) {
    
    if ( ! isset( $_POST['wpls_lead_nonce'] ) || ! wp_verify_nonce( $_POST['wpls_lead_nonce'], 'wpls_lead_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Felder für Kontakt und Firma (Spez 2.1)
    $fields_text = [
        '_lead_first_name', '_lead_last_name', '_lead_role', 
        '_lead_company_name', '_lead_company_industry', '_lead_website'
    ];
    foreach ( $fields_text as $field ) {
        if ( isset( $_POST[$field] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
    
    if ( isset( $_POST['_lead_contact_email'] ) ) {
        update_post_meta( $post_id, '_lead_contact_email', sanitize_email( $_POST['_lead_contact_email'] ) );
    }
    if ( isset( $_POST['_lead_contact_phone'] ) ) {
        update_post_meta( $post_id, '_lead_contact_phone', sanitize_text_field( $_POST['_lead_contact_phone'] ) );
    }
    if ( isset( $_POST['_lead_company_address'] ) ) {
        update_post_meta( $post_id, '_lead_company_address', sanitize_textarea_field( $_POST['_lead_company_address'] ) );
    }

    // Felder für Tracking (Spez 2.1)
    $fields_tracking = [ '_lead_status', '_lead_showed_call' ];
    foreach ( $fields_tracking as $field ) {
        if ( isset( $_POST[$field] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
    
    // Checkboxen
    update_post_meta( $post_id, '_lead_started_sequence', isset( $_POST['_lead_started_sequence'] ) ? '1' : '0' );
    update_post_meta( $post_id, '_lead_call_scheduled', isset( $_POST['_lead_call_scheduled'] ) ? '1' : '0' );
    
    // Zahlen
    if ( isset( $_POST['_lead_follow_ups_sent'] ) ) {
        update_post_meta( $post_id, '_lead_follow_ups_sent', intval( $_POST['_lead_follow_ups_sent'] ) );
    }
    
    // Automatischen Titel setzen (Vorname, Nachname)
    $title = 'Lead';
    if( !empty($_POST['_lead_last_name']) && !empty($_POST['_lead_first_name']) ) {
        $title = sanitize_text_field( $_POST['_lead_last_name'] ) . ', ' . sanitize_text_field( $_POST['_lead_first_name'] );
    } elseif ( !empty($_POST['_lead_contact_email']) ) {
        $title = sanitize_email( $_POST['_lead_contact_email'] );
    }
    
    // Verhindert Endlosschleife, indem wir 'save_post' entfernen, updaten, und wieder hinzufügen
    remove_action( 'save_post_lead', 'wpls_save_lead_metabox_data' );
    wp_update_post( ['ID' => $post_id, 'post_title' => $title] );
    add_action( 'save_post_lead', 'wpls_save_lead_metabox_data', 10, 1 );

}
add_action( 'save_post_lead', 'wpls_save_lead_metabox_data', 10, 1 );

/**
 * Speichert die Meta-Daten für E-Mail-Vorlagen.
 */
function wpls_save_email_template_metabox_data( $post_id ) {
    
    if ( ! isset( $_POST['wpls_template_nonce'] ) || ! wp_verify_nonce( $_POST['wpls_template_nonce'], 'wpls_template_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Felder (Spez 2.2)
    if ( isset( $_POST['_template_email_subject'] ) ) {
        update_post_meta( $post_id, '_template_email_subject', sanitize_text_field( $_POST['_template_email_subject'] ) );
    }
    if ( isset( $_POST['_template_type'] ) ) {
        update_post_meta( $post_id, '_template_type', sanitize_text_field( $_POST['_template_type'] ) );
    }
}
add_action( 'save_post_email_template', 'wpls_save_email_template_metabs_data', 10, 1 );

/**
 * Speichert die Meta-Daten für Logs.
 */
function wpls_save_lead_log_metabox_data( $post_id ) {
    
    if ( ! isset( $_POST['wpls_log_nonce'] ) || ! wp_verify_nonce( $_POST['wpls_log_nonce'], 'wpls_log_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Felder (Spez 2.3)
    if ( isset( $_POST['_log_lead_id'] ) ) {
        update_post_meta( $post_id, '_log_lead_id', intval( $_POST['_log_lead_id'] ) );
    }
    if ( isset( $_POST['_log_type'] ) ) {
        update_post_meta( $post_id, '_log_type', sanitize_text_field( $_POST['_log_type'] ) );
    }
}
add_action( 'save_post_lead_log', 'wpls_save_lead_log_metabox_data', 10, 1 );

?>