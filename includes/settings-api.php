<?php
/**
 * Erstellt die Einstellungsseite mit der WordPress Settings API.
 * (Neu generiert, um [redacted] Fehler zu beheben und API-Tab zu entfernen)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * HTML für die Einstellungsseite (Wrapper und Tabs)
 * (Callback von add_submenu_page in admin-menu.php)
 */
function wpls_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Aktiven Tab holen
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p><?php _e( 'Konfigurieren Sie die globalen Einstellungen für den Lead Sequencer.', 'wp-lead-sequencer' ); ?></p>
        
        <!-- Tabs Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=wpls-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                <?php _e( 'Allgemein', 'wp-lead-sequencer' ); ?>
            </a>
            <a href="?page=wpls-settings&tab=logic" class="nav-tab <?php echo $active_tab == 'logic' ? 'nav-tab-active' : ''; ?>">
                <?php _e( 'Sequenz-Logik', 'wp-lead-sequencer' ); ?>
            </a>
        </h2>
        
        <form action="options.php" method="post">
            <?php
            // Korrekte Settings-Gruppe basierend auf dem Tab laden
            if ( $active_tab == 'logic' ) {
                settings_fields( 'wpls_settings_logic_group' );
                do_settings_sections( 'wpls-settings-tab-logic' );
            } else {
                settings_fields( 'wpls_settings_general_group' );
                do_settings_sections( 'wpls-settings-tab-general' );
            }
            
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Registriert die Einstellungen, Sektionen und Felder.
 */
function wpls_register_settings() {

    // --- GRUPPE 1: Allgemein (Tab 1) ---
    register_setting(
        'wpls_settings_general_group', // Options-Gruppe
        'wpls_settings', // Name in wp_options
        'wpls_settings_sanitize' // Sanitize-Callback
    );

    // Sektion 1: Absender-Details
    add_settings_section(
        'wpls_settings_section_sender', // ID
        __( 'Absender-Einstellungen', 'wp-lead-sequencer' ), // Titel
        'wpls_settings_section_sender_callback', // Callback
        'wpls-settings-tab-general' // Seite
    );

    add_settings_field(
        'sender_name',
        __( 'Absender-Name', 'wp-lead-sequencer' ),
        'wpls_settings_field_sender_name_callback',
        'wpls-settings-tab-general',
        'wpls_settings_section_sender'
    );
    
    add_settings_field(
        'sender_email',
        __( 'Absender-E-Mail', 'wp-lead-sequencer' ),
        'wpls_settings_field_sender_email_callback',
        'wpls-settings-tab-general',
        'wpls_settings_section_sender'
    );

    // --- GRUPPE 2: Sequenz-Logik (Tab 2) ---
    register_setting(
        'wpls_settings_logic_group', // Eigene Gruppe für diesen Tab
        'wpls_settings', // Nutzt denselben Options-Namen (wpls_settings)
        'wpls_settings_sanitize' // Selber Sanitize-Callback
    );
    
    // Sektion 2: Sequenz-Logik
    add_settings_section(
        'wpls_settings_section_logic',
        __( 'Automatisierungs-Logik', 'wp-lead-sequencer' ),
        'wpls_settings_section_logic_callback',
        'wpls-settings-tab-logic' // Seite für Tab 2
    );
    
    add_settings_field(
        'max_follow_ups',
        __( 'Maximale Follow-ups', 'wp-lead-sequencer' ),
        'wpls_settings_field_max_follow_ups_callback',
        'wpls-settings-tab-logic',
        'wpls_settings_section_logic'
    );
    
    add_settings_field(
        'days_between_follow_ups',
        __( 'Tage zwischen Follow-ups', 'wp-lead-sequencer' ),
        'wpls_settings_field_days_between_callback',
        'wpls-settings-tab-logic',
        'wpls_settings_section_logic'
    );
}
add_action( 'admin_init', 'wpls_register_settings' );


// --- Sektions-Callbacks ---

function wpls_settings_section_sender_callback() {
    echo '<p>' . __( 'Diese Angaben werden als "Von"-Adresse für alle Follow-up-E-Mails verwendet.', 'wp-lead-sequencer' ) . '</p>';
}

function wpls_settings_section_logic_callback() {
    echo '<p>' . __( 'Steuern Sie hier die Logik der automatisierten E-Mail-Sequenzen.', 'wp-lead-sequencer' ) . '</p>';
}


// --- Feld-Callbacks (Rendern das HTML) ---

function wpls_get_settings_option( $field_id ) {
    $options = get_option( 'wpls_settings' );
    return $options[$field_id] ?? '';
}

// Tab 1: Allgemein
function wpls_settings_field_sender_name_callback() {
    $value = wpls_get_settings_option( 'sender_name' );
    echo '<input type="text" name="wpls_settings[sender_name]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . get_bloginfo( 'name' ) . '" />';
}

function wpls_settings_field_sender_email_callback() {
    $value = wpls_get_settings_option( 'sender_email' );
    echo '<input type="email" name="wpls_settings[sender_email]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . get_bloginfo( 'admin_email' ) . '" />';
}

// Tab 2: Sequenz-Logik
function wpls_settings_field_max_follow_ups_callback() {
    $value = wpls_get_settings_option( 'max_follow_ups' );
    echo '<input type="number" name="wpls_settings[max_follow_ups]" value="' . esc_attr( $value ) . '" class="small-text" min="1" max="10" placeholder="3" />';
    echo ' <p class="description">' . __( 'Maximale Anzahl an E-Mails, die ein Lead in einer Sequenz erhält.', 'wp-lead-sequencer' ) . '</p>';
}

function wpls_settings_field_days_between_callback() {
    $value = wpls_get_settings_option( 'days_between_follow_ups' );
    echo '<input type="number" name="wpls_settings[days_between_follow_ups]" value="' . esc_attr( $value ) . '" class="small-text" min="1" max="30" placeholder="3" />';
    echo ' <p class="description">' . __( 'Anzahl der Tage, die das System wartet, bevor das nächste Follow-up gesendet wird.', 'wp-lead-sequencer' ) . '</p>';
}


// --- Sanitize-Callback ---

/**
 * Bereinigt die Eingaben der Einstellungsseite.
 */
function wpls_settings_sanitize( $input ) {
    $sanitized_input = get_option( 'wpls_settings' ); // Holt bestehende Werte

    if ( isset( $input['sender_name'] ) ) {
        $sanitized_input['sender_name'] = sanitize_text_field( $input['sender_name'] );
    }
    if ( isset( $input['sender_email'] ) ) {
        $sanitized_input['sender_email'] = sanitize_email( $input['sender_email'] );
    }
    if ( isset( $input['max_follow_ups'] ) ) {
        $sanitized_input['max_follow_ups'] = intval( $input['max_follow_ups'] );
    }
    if ( isset( $input['days_between_follow_ups'] ) ) {
        $sanitized_input['days_between_follow_ups'] = intval( $input['days_between_follow_ups'] );
    }
    
    return $sanitized_input;
}
?>