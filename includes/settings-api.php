<?php
/**
 * Erstellt die Einstellungsseite mit der WordPress Settings API.
 * (Aktualisiert um "Stunden" statt "Tage" und Max. Follow-ups auf 5)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * HTML für die Einstellungsseite (Wrapper und Tabs)
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
            <a href="?page=wpls-settings&tab=integrations" class="nav-tab <?php echo $active_tab == 'integrations' ? 'nav-tab-active' : ''; ?>">
                <?php _e( 'Integrationen (API/Webhooks)', 'wp-lead-sequencer' ); ?>
            </a>
        </h2>
        
        <form action="options.php" method="post">
            <?php
            // Korrekte Settings-Gruppe basierend auf dem Tab laden
            if ( $active_tab == 'logic' ) {
                settings_fields( 'wpls_settings_logic_group' );
                do_settings_sections( 'wpls-settings-tab-logic' );
            } 
            elseif ( $active_tab == 'integrations' ) {
                settings_fields( 'wpls_settings_integrations_group' );
                do_settings_sections( 'wpls-settings-tab-integrations' );
            } 
            else {
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
 * (Aktualisiert auf "Stunden")
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
        'hours_between_follow_ups', // <-- Geändert von 'days_between_follow_ups'
        __( 'Stunden zwischen Follow-ups', 'wp-lead-sequencer' ), // <-- Geändert
        'wpls_settings_field_hours_between_callback', // <-- Geändert
        'wpls-settings-tab-logic',
        'wpls_settings_section_logic'
    );

    // --- GRUPPE 3: Integrationen (Tab 3) ---
    register_setting(
        'wpls_settings_integrations_group',
        'wpls_settings',
        'wpls_settings_sanitize'
    );

    // Sektion 3a: Plugin API Key (für n8n etc. EINGEHEND)
    add_settings_section(
        'wpls_settings_section_plugin_api',
        __( 'Plugin API (Eingehend für n8n, Zapier etc.)', 'wp-lead-sequencer' ),
        'wpls_settings_section_plugin_api_callback',
        'wpls-settings-tab-integrations'
    );

    add_settings_field(
        'plugin_api_key',
        __( 'Geheimer API-Schlüssel (Bearer Token)', 'wp-lead-sequencer' ),
        'wpls_settings_field_plugin_api_key_callback',
        'wpls-settings-tab-integrations',
        'wpls_settings_section_plugin_api'
    );
    
    // Sektion 3c: Outbound Webhooks (AUSGEHEND an n8n)
    add_settings_section(
        'wpls_settings_section_outbound_webhooks',
        __( 'Outbound Webhooks (Ausgehend an n8n, Zapier etc.)', 'wp-lead-sequencer' ),
        'wpls_settings_section_outbound_webhooks_callback',
        'wpls-settings-tab-integrations'
    );

    add_settings_field(
        'n8n_webhook_lead_created',
        __( 'Lead Erstellt (Webhook-URL)', 'wp-lead-sequencer' ),
        'wpls_settings_field_outbound_webhook_callback',
        'wpls-settings-tab-integrations',
        'wpls_settings_section_outbound_webhooks',
        ['id' => 'n8n_webhook_lead_created']
    );
    
    add_settings_field(
        'n8n_webhook_lead_booked',
        __( 'Call Gebucht (Webhook-URL)', 'wp-lead-sequencer' ),
        'wpls_settings_field_outbound_webhook_callback',
        'wpls-settings-tab-integrations',
        'wpls_settings_section_outbound_webhooks',
        ['id' => 'n8n_webhook_lead_booked']
    );
    
    add_settings_field(
        'n8n_webhook_lead_sequence_started',
        __( 'Sequenz Gestartet (Webhook-URL)', 'wp-lead-sequencer' ),
        'wpls_settings_field_outbound_webhook_callback',
        'wpls-settings-tab-integrations',
        'wpls_settings_section_outbound_webhooks',
        ['id' => 'n8n_webhook_lead_sequence_started']
    );
    
    add_settings_field(
        'n8n_webhook_email_sent',
        __( 'E-Mail Gesendet (Webhook-URL)', 'wp-lead-sequencer' ),
        'wpls_settings_field_outbound_webhook_callback',
        'wpls-settings-tab-integrations',
        'wpls_settings_section_outbound_webhooks',
        ['id' => 'n8n_webhook_email_sent']
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

function wpls_settings_section_plugin_api_callback() {
    echo '<p>' . __( 'Sichern Sie Ihre Plugin-API (z.B. für n8n oder Zapier) mit einem geheimen "Bearer Token".', 'wp-lead-sequencer' ) . '</p>';
    echo '<p>' . __( 'n8n kann diesen Schlüssel verwenden, um Leads über die Endpunkte zu aktualisieren (z.B. Status auf "gebucht" setzen).', 'wp-lead-sequencer' ) . '</p>';
    echo '<p>' . __( 'Endpunkte: <code>/wp-json/lead-sequencer/v1/leads/create</code> (Upsert) und <code>/wp-json/lead-sequencer/v1/leads/find?email=...</code>', 'wp-lead-sequencer' ) . '</p>';
}

function wpls_settings_section_outbound_webhooks_callback() {
    echo '<p>' . __( 'Benachrichtigen Sie externe Dienste (wie n8n), wenn Aktionen in diesem Plugin stattfinden.', 'wp-lead-sequencer' ) . '</p>';
    echo '<p>' . __( 'Fügen Sie die Webhook-URL von n8n in das entsprechende Feld ein. Das Plugin sendet dann automatisch die vollständigen Lead-Daten (als JSON) an diese URL, wenn das Ereignis eintritt.', 'wp-lead-sequencer' ) . '</p>';
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
    echo '<input type="number" name="wpls_settings[max_follow_ups]" value="' . esc_attr( $value ) . '" class="small-text" min="1" max="10" placeholder="5" />'; // <-- Geändert auf 5
    echo ' <p class="description">' . __( 'Maximale Anzahl an E-Mails, die ein Lead in einer Sequenz erhält (z.B. 5).', 'wp-lead-sequencer' ) . '</p>';
}

function wpls_settings_field_hours_between_callback() { // <-- Umbenannt
    $value = wpls_get_settings_option( 'hours_between_follow_ups' );
    echo '<input type="number" name="wpls_settings[hours_between_follow_ups]" value="' . esc_attr( $value ) . '" class="small-text" min="1" max="168" placeholder="24" />'; // <-- Geändert
    echo ' <p class="description">' . __( 'Anzahl der Stunden, die das System wartet, bevor das nächste Follow-up gesendet wird (z.B. 24 für 1 Tag).', 'wp-lead-sequencer' ) . '</p>'; // <-- Geändert
}

// Tab 3: Integrationen
function wpls_settings_field_plugin_api_key_callback() {
    $value = wpls_get_settings_option( 'plugin_api_key' );
    $placeholder = ! empty( $value ) ? '********' : '';
    
    echo '<input type="password" name="wpls_settings[plugin_api_key]" value="' . esc_attr( $placeholder ) . '" class="regular-text" />';
    echo ' <p class="description">' . __( 'Tragen Sie hier einen sicheren Schlüssel ein (z.B. 32+ Zeichen). Bleibt gespeichert, wenn das Feld leer gelassen wird.', 'wp-lead-sequencer' ) . '</p>';
}

function wpls_settings_field_outbound_webhook_callback( $args ) {
    $id = $args['id'];
    $value = wpls_get_settings_option( $id );
    echo '<input type="url" name="wpls_settings[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="large-text" placeholder="' . __( 'https://ihr.n8n.server/webhook/xyz', 'wp-lead-sequencer' ) . '" />';
}


// --- Sanitize-Callback ---

/**
 * Bereinigt die Eingaben der Einstellungsseite.
 * (Aktualisiert auf "Stunden")
 */
function wpls_settings_sanitize( $input ) {
    // Hole bestehende (alte) Werte, um sie nicht zu überschreiben
    $sanitized_input = get_option( 'wpls_settings' );
    if ( ! is_array( $sanitized_input ) ) {
        $sanitized_input = array();
    }

    if ( isset( $input['sender_name'] ) ) {
        $sanitized_input['sender_name'] = sanitize_text_field( $input['sender_name'] );
    }
    if ( isset( $input['sender_email'] ) ) {
        $sanitized_input['sender_email'] = sanitize_email( $input['sender_email'] );
    }
    if ( isset( $input['max_follow_ups'] ) ) {
        $sanitized_input['max_follow_ups'] = intval( $input['max_follow_ups'] );
    }
    
    // --- GEÄNDERT ---
    if ( isset( $input['hours_between_follow_ups'] ) ) {
        $sanitized_input['hours_between_follow_ups'] = intval( $input['hours_between_follow_ups'] );
    }
    // Entferne das alte "Tage"-Feld, falls es noch existiert
    unset($sanitized_input['days_between_follow_ups']);
    // --- ENDE ÄNDERUNG ---
    
    // Speichert den Plugin-API-Schlüssel nur, wenn er geändert wurde
    if ( isset( $input['plugin_api_key'] ) && ! empty( $input['plugin_api_key'] ) ) {
        if ( $input['plugin_api_key'] !== '********' ) {
            $sanitized_input['plugin_api_key'] = sanitize_text_field( $input['plugin_api_key'] );
        }
    }
    
    // Speichert die n8n-Webhook-URLs
    $outbound_webhooks = array(
        'n8n_webhook_lead_created',
        'n8n_webhook_lead_booked',
        'n8n_webhook_lead_sequence_started',
        'n8n_webhook_email_sent',
    );
    foreach ( $outbound_webhooks as $key ) {
        if ( isset( $input[$key] ) ) {
            $sanitized_input[$key] = esc_url_raw( $input[$key] );
        }
    }
    
    // Alte, nicht mehr verwendete Calendly-Felder entfernen
    unset($sanitized_input['calendly_personal_access_token']);
    unset($sanitized_input['calendly_webhook_secret']);
    unset($sanitized_input['calendly_event_urls']);
    unset($sanitized_input['calendly_webhook_uuid']);

    return $sanitized_input;
}
?>