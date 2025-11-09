<?php
/**
 * Registriert die Custom Post Types (CPTs) und Metaboxen.
 * (Vollständig neu generiert, um [redacted] Fehler zu beheben)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Registriert alle CPTs beim Initialisieren.
 */
function wpls_register_post_types() {

    // --- 1. CPT: Leads (Spez 2.1) ---
    $labels_lead = array(
        'name'               => _x( 'Leads', 'post type general name', 'wp-lead-sequencer' ),
        'singular_name'      => _x( 'Lead', 'post type singular name', 'wp-lead-sequencer' ),
        'menu_name'          => _x( 'Leads', 'admin menu', 'wp-lead-sequencer' ),
        'name_admin_bar'     => _x( 'Lead', 'add new on admin bar', 'wp-lead-sequencer' ),
        'add_new'            => __( 'Neuen Lead', 'wp-lead-sequencer' ),
        'add_new_item'       => __( 'Neuen Lead hinzufügen', 'wp-lead-sequencer' ),
        'new_item'           => __( 'Neuer Lead', 'wp-lead-sequencer' ),
        'edit_item'          => __( 'Lead bearbeiten', 'wp-lead-sequencer' ),
        'view_item'          => __( 'Lead ansehen', 'wp-lead-sequencer' ),
        'all_items'          => __( 'Alle Leads', 'wp-lead-sequencer' ),
        'search_items'       => __( 'Leads suchen', 'wp-lead-sequencer' ),
        'not_found'          => __( 'Keine Leads gefunden.', 'wp-lead-sequencer' ),
        'not_found_in_trash' => __( 'Keine Leads im Papierkorb gefunden.', 'wp-lead-sequencer' )
    );
    $args_lead = array(
        'labels'             => $labels_lead,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => false, // Wird manuell unter "Lead Sequencer" hinzugefügt
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'lead' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title' ), // 'post_title' wird programmatisch befüllt (Spez 2.1)
        'show_in_rest'       => true, // Wichtig für zukünftige UI
    );
    register_post_type( 'lead', $args_lead );

    // --- 2. CPT: E-Mail-Vorlagen (Spez 2.2) ---
    $labels_template = array(
        'name'               => _x( 'E-Mail-Vorlagen', 'post type general name', 'wp-lead-sequencer' ),
        'singular_name'      => _x( 'E-Mail-Vorlage', 'post type singular name', 'wp-lead-sequencer' ),
        'menu_name'          => _x( 'E-Mail-Vorlagen', 'admin menu', 'wp-lead-sequencer' ),
        'add_new'            => __( 'Neue Vorlage', 'wp-lead-sequencer' ),
        'add_new_item'       => __( 'Neue Vorlage hinzufügen', 'wp-lead-sequencer' ),
        'edit_item'          => __( 'Vorlage bearbeiten', 'wp-lead-sequencer' ),
    );
    $args_template = array(
        'labels'             => $labels_template,
        'public'             => false, // Nicht öffentlich sichtbar
        'show_ui'            => true,
        'show_in_menu'       => false, // Wird manuell hinzugefügt
        'capability_type'    => 'post',
        'supports'           => array( 'title', 'editor' ), // 'post_content' ist der E-Mail-Body (Spez 2.2)
        'show_in_rest'       => true,
    );
    register_post_type( 'email_template', $args_template );

    // --- 3. CPT: Lead-Log (Spez 2.3) ---
    $labels_log = array(
        'name'               => _x( 'Logs', 'post type general name', 'wp-lead-sequencer' ),
        'singular_name'      => _x( 'Log', 'post type singular name', 'wp-lead-sequencer' ),
        'menu_name'          => _x( 'Logs', 'admin menu', 'wp-lead-sequencer' ),
        'edit_item'          => __( 'Log bearbeiten', 'wp-lead-sequencer' ),
        'view_item'          => __( 'Log ansehen', 'wp-lead-sequencer' ),
        'all_items'          => __( 'Alle Logs', 'wp-lead-sequencer' ),
    );
    $args_log = array(
        'labels'             => $labels_log,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => false, // Wird manuell hinzugefügt
        'capability_type'    => 'post',
        'capabilities'       => array(
            'create_posts' => false, // Verhindert "Neu hinzufügen" (Logs werden nur programmatisch erstellt)
        ),
        'map_meta_cap'       => true,
        'supports'           => array( 'title', 'content' ), // 'post_content' sind die Details (Spez 2.3)
    );
    register_post_type( 'lead_log', $args_log );
}
add_action( 'init', 'wpls_register_post_types' );


//
// --- METABOXEN REGISTRIEREN (Spez 2.1, 2.2) ---
//

/**
 * Registriert die Metaboxen für die CPTs.
 */
function wpls_add_meta_boxes() {
    // 1. Metabox für 'lead' (Alle Felder)
    add_meta_box(
        'wpls_lead_details_metabox', // ID
        __( 'Lead-Informationen (Kontaktdaten)', 'wp-lead-sequencer' ), // Titel
        'wpls_render_lead_details_metabox', // Callback
        'lead', // CPT
        'normal', // Kontext
        'high' // Priorität
    );
    add_meta_box(
        'wpls_lead_tracking_metabox',
        __( 'Lead-Tracking (Intern)', 'wp-lead-sequencer' ),
        'wpls_render_lead_tracking_metabox',
        'lead',
        'side', // Seitenleiste
        'default'
    );
    
    // 2. Metabox für 'email_template' (Betreff & Typ)
    add_meta_box(
        'wpls_template_details_metabox',
        __( 'Vorlagen-Einstellungen', 'wp-lead-sequencer' ),
        'wpls_render_template_details_metabox',
        'email_template',
        'normal',
        'high'
    );

    // 3. Metabox für 'lead_log' (Lead ID & Typ)
    add_meta_box(
        'wpls_log_details_metabox',
        __( 'Log-Details', 'wp-lead-sequencer' ),
        'wpls_render_log_details_metabox',
        'lead_log',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'wpls_add_meta_boxes' );


//
// --- METABOXEN RENDERN (HTML-AUSGABE) ---
//

/**
 * Rendert die Metabox für 'lead' (Kontaktdaten).
 */
function wpls_render_lead_details_metabox( $post ) {
    // Nonce für Sicherheit
    wp_nonce_field( 'wpls_save_lead_meta', 'wpls_lead_nonce' );

    // Felder aus Spez 2.1 (Kontaktdaten)
    $fields = array(
        '_lead_first_name' => __( 'Vorname', 'wp-lead-sequencer' ),
        '_lead_last_name'  => __( 'Nachname', 'wp-lead-sequencer' ),
        '_lead_contact_email' => __( 'E-Mail', 'wp-lead-sequencer' ),
        '_lead_contact_phone' => __( 'Telefon', 'wp-lead-sequencer' ),
        '_lead_website' => __( 'Webseite', 'wp-lead-sequencer' ),
    );
    // Felder aus Spez 2.1 (Firma)
    $company_fields = array(
        '_lead_role' => __( 'Rolle/Position', 'wp-lead-sequencer' ),
        '_lead_company_name' => __( 'Firmenname', 'wp-lead-sequencer' ),
        '_lead_company_industry' => __( 'Branche', 'wp-lead-sequencer' ),
        '_lead_company_address' => __( 'Adresse', 'wp-lead-sequencer' ),
    );
    
    echo '<table class="form-table"><tbody>';
    
    // Kontaktdaten
    foreach ($fields as $key => $label) {
        $value = get_post_meta( $post->ID, $key, true );
        $type = ($key == '_lead_contact_email') ? 'email' : (($key == '_lead_website') ? 'url' : 'text');
        echo '<tr>';
        echo '<th scope="row"><label for="' . $key . '">' . $label . '</label></th>';
        echo '<td><input type="' . $type . '" name="' . $key . '" id="' . $key . '" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
        echo '</tr>';
    }
    
    // Trenner
    echo '<tr><td colspan="2"><hr><h4>' . __('Firmeninformationen', 'wp-lead-sequencer') . '</h4></td></tr>';

    // Firmendaten
    foreach ($company_fields as $key => $label) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<tr>';
        echo '<th scope="row"><label for="' . $key . '">' . $label . '</label></th>';
        echo '<td><input type="text" name="' . $key . '" id="' . $key . '" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

/**
 * Rendert die Metabox für 'lead' (Tracking-Daten).
 */
function wpls_render_lead_tracking_metabox( $post ) {
    // Nonce wurde bereits in der Haupt-Metabox gesetzt
    
    // Status (Spez 2.1)
    $status = get_post_meta( $post->ID, '_lead_status', true ) ?: 'new';
    $status_options = array(
        'new' => __( 'Neu', 'wp-lead-sequencer' ),
        'sequencing' => __( 'In Sequenz', 'wp-lead-sequencer' ),
        'booked' => __( 'Call gebucht', 'wp-lead-sequencer' ),
        'stopped' => __( 'Gestoppt (Manuell/Fehler/Max)', 'wp-lead-sequencer' ),
    );
    echo '<p><strong>' . __( 'Lead-Status', 'wp-lead-sequencer' ) . ':</strong><br>';
    echo '<select name="_lead_status" style="width:100%;">';
    foreach ($status_options as $key => $label) {
        echo '<option value="' . $key . '" ' . selected( $status, $key, false ) . '>' . $label . '</option>';
    }
    echo '</select></p>';

    // Call-Status (Spez 2.1)
    $showed = get_post_meta( $post->ID, '_lead_showed_call', true );
    $showed_options = array(
        '' => __( 'N/A (Kein Call gebucht)', 'wp-lead-sequencer' ),
        'yes' => __( 'Ja (Call wahrgenommen)', 'wp-lead-sequencer' ),
        'no' => __( 'Nein (No-Show)', 'wp-lead-sequencer' ),
        'followed_up' => __( 'No-Show (kontaktiert)', 'wp-lead-sequencer' ),
    );
    echo '<p><strong>' . __( 'Call-Status (No-Show)', 'wp-lead-sequencer' ) . ':</strong><br>';
    echo '<select name="_lead_showed_call" style="width:100%;">';
    foreach ($showed_options as $key => $label) {
        echo '<option value="' . $key . '" ' . selected( $showed, $key, false ) . '>' . $label . '</option>';
    }
    echo '</select></p>';
    
    // Andere Tracking-Felder (nur Anzeige)
    $sent = (int) get_post_meta( $post->ID, '_lead_follow_ups_sent', true );
    $last_date = get_post_meta( $post->ID, '_lead_sequence_last_date', true );
    
    echo '<hr><p><strong>' . __( 'Gesendete Follow-ups', 'wp-lead-sequencer' ) . ':</strong> ' . $sent . '</p>';
    echo '<p><strong>' . __( 'Letzter Kontakt', 'wp-lead-sequencer' ) . ':</strong><br> ' . ($last_date ? date('Y-m-d H:i:s', $last_date) : 'N/A') . '</p>';
}

/**
 * Rendert die Metabox für 'email_template'.
 * (Aktualisiert mit Platzhalter-Box - Aufgabe 2)
 */
function wpls_render_template_details_metabox( $post ) {
    // Nonce für Sicherheit (Abgestimmt auf wpls_save_template_meta)
    wp_nonce_field( 'wpls_save_template_meta', 'wpls_template_nonce' );

    // Werte laden
    $subject = get_post_meta( $post->ID, '_template_email_subject', true );
    $type    = get_post_meta( $post->ID, '_template_type', true );

    // DEFINIERTE PLATZHALTER (NEU)
    $placeholders = array(
        '[FIRST_NAME]' => 'Vorname des Leads',
        '[LAST_NAME]'  => 'Nachname des Leads',
        '[EMAIL]'      => 'E-Mail des Leads',
        '[COMPANY]'    => 'Firma des Leads',
        '[ROLE]'       => 'Rolle des Leads',
    );
    ?>
    
    <!-- NEU: Platzhalter-Box -->
    <div class="wpls-placeholders-box">
        <h4><?php _e( 'Verfügbare Platzhalter', 'wp-lead-sequencer' ); ?></h4>
        <?php foreach ( $placeholders as $code => $desc ) : ?>
            <code title="<?php echo esc_attr( $desc ); ?>"><?php echo esc_html( $code ); ?></code>
        <?php endforeach; ?>
    </div>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row">
                <!-- ID und For-Attribut korrigiert (mit Underscore) -->
                <label for="_template_email_subject"><?php _e( 'E-Mail-Betreff', 'wp-lead-sequencer' ); ?></label>
            </th>
            <td>
                <!-- Name-Attribut korrigiert (mit Underscore) -->
                <input type="text" id="_template_email_subject" name="_template_email_subject" value="<?php echo esc_attr( $subject ); ?>" class="large-text" required />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <!-- ID und For-Attribut korrigiert (mit Underscore) -->
                <label for="_template_type"><?php _e( 'Vorlagen-Typ (Zweck)', 'wp-lead-sequencer' ); ?></label>
            </th>
            <td>
                <!-- Name-Attribut korrigiert (mit Underscore) -->
                <select id="_template_type" name="_template_type" required>
                    <option value=""><?php _e( '-- Zweck auswählen --', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_1" <?php selected( $type, 'follow_up_1' ); ?>><?php _e( 'Follow Up 1 (Start-E-Mail)', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_2" <?php selected( $type, 'follow_up_2' ); ?>><?php _e( 'Follow Up 2', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_3" <?php selected( $type, 'follow_up_3' ); ?>><?php _e( 'Follow Up 3', 'wp-lead-sequencer' ); ?></option>
                    <option value="follow_up_4" <?php selected( $type, 'follow_up_4' ); ?>><?php _e( 'Follow Up 4 (Max)', 'wp-lead-sequencer' ); ?></option>
                    <option value="no_show" <?php selected( $type, 'no_show' ); ?>><?php _e( 'No-Show E-Mail', 'wp-lead-sequencer' ); ?></option>
                </select>
                <p class="description"><?php _e( 'Dies weist die Logik an, wann diese E-Mail gesendet werden soll.', 'wp-lead-sequencer' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Rendert die Metabox für 'lead_log'. (Nur Anzeige)
 */
function wpls_render_log_details_metabox( $post ) {
    $lead_id = get_post_meta( $post->ID, '_log_lead_id', true );
    $log_type = get_post_meta( $post->ID, '_log_type', true );
    
    echo '<p><strong>' . __( 'Log-Typ', 'wp-lead-sequencer' ) . ':</strong> ' . esc_html( $log_type ) . '</p>';
    
    if ( $lead_id ) {
        $lead_link = get_edit_post_link( (int) $lead_id );
        echo '<p><strong>' . __( 'Zugehöriger Lead', 'wp-lead-sequencer' ) . ':</strong><br>';
        echo '<a href="' . esc_url( $lead_link ) . '">Lead #' . (int) $lead_id . ' ansehen</a></p>';
    }
}


//
// --- METADATEN SPEICHERN ---
//

/**
 * Speichert die Meta-Daten für 'lead' CPT.
 */
function wpls_save_lead_meta( $post_id ) {
    // 1. Nonce prüfen
    if ( ! isset( $_POST['wpls_lead_nonce'] ) || ! wp_verify_nonce( $_POST['wpls_lead_nonce'], 'wpls_save_lead_meta' ) ) {
        return;
    }
    // 2. Autosave ignorieren
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // 3. Berechtigungen prüfen
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Felder aus Spez 2.1 (Alle Felder in der Metabox)
    $fields_to_save = array(
        '_lead_first_name',
        '_lead_last_name',
        '_lead_contact_email',
        '_lead_contact_phone',
        '_lead_website',
        '_lead_role',
        '_lead_company_name',
        '_lead_company_industry',
        '_lead_company_address',
        '_lead_status',
        '_lead_showed_call',
    );
    
    foreach ($fields_to_save as $key) {
        if ( isset( $_POST[$key] ) ) {
            $value = $_POST[$key];
            
            // Spezifische Bereinigung
            if ($key == '_lead_contact_email') {
                $value = sanitize_email($value);
            } elseif ($key == '_lead_website') {
                $value = esc_url_raw($value);
            } else {
                $value = sanitize_text_field($value);
            }
            
            update_post_meta( $post_id, $key, $value );
        }
    }
}
add_action( 'save_post_lead', 'wpls_save_lead_meta' );

/**
 * Speichert die Meta-Daten für 'email_template' CPT.
 */
function wpls_save_template_meta( $post_id ) {
    if ( ! isset( $_POST['wpls_template_nonce'] ) || ! wp_verify_nonce( $_POST['wpls_template_nonce'], 'wpls_save_template_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Felder aus Spez 2.2
    $fields_to_save = array(
        '_template_email_subject',
        '_template_type',
    );
    
    foreach ($fields_to_save as $key) {
        if ( isset( $_POST[$key] ) ) {
            update_post_meta( $post_id, $key, sanitize_text_field( $_POST[$key] ) );
        }
    }
}
add_action( 'save_post_email_template', 'wpls_save_template_meta' );


//
// --- ADMIN-SPALTEN ANPASSEN (Spez 3.5) ---
//

/**
 * Passt die Spalten für 'lead_log' CPT an.
 */
function wpls_set_lead_log_columns( $columns ) {
    // Alte Spalten entfernen (Titel behalten wir)
    unset( $columns['date'] );
    
    $columns['log_type'] = __( 'Log-Typ', 'wp-lead-sequencer' );
    $columns['lead_id'] = __( 'Zugehöriger Lead', 'wp-lead-sequencer' );
    $columns['log_date'] = __( 'Datum', 'wp-lead-sequencer' ); // Datum wieder hinzufügen (für Sortierung)

    return $columns;
}
add_filter( 'manage_lead_log_posts_columns', 'wpls_set_lead_log_columns' );

/**
 * Füllt den Inhalt der benutzerdefinierten 'lead_log'-Spalten.
 */
function wpls_custom_lead_log_column( $column, $post_id ) {
    switch ( $column ) {
        case 'log_type':
            echo esc_html( get_post_meta( $post_id, '_log_type', true ) );
            break;

        case 'lead_id':
            $lead_id = (int) get_post_meta( $post_id, '_log_lead_id', true );
            if ( $lead_id ) {
                $lead_link = get_edit_post_link( $lead_id );
                echo '<a href="' . esc_url( $lead_link ) . '">' . esc_html( get_the_title( $lead_id ) ) . ' (ID: ' . $lead_id . ')</a>';
            } else {
                echo 'N/A';
            }
            break;
        
        case 'log_date':
            echo get_the_date( 'Y-m-d H:i:s' );
            break;
    }
}
add_action( 'manage_lead_log_posts_custom_column', 'wpls_custom_lead_log_column', 10, 2 );

/**
 * Macht die neuen 'lead_log'-Spalten sortierbar.
 */
function wpls_lead_log_sortable_columns( $columns ) {
    $columns['log_type'] = '_log_type';
    $columns['log_date'] = 'date'; // Nach dem Post-Datum sortieren
    return $columns;
}
add_filter( 'manage_edit-lead_log_sortable_columns', 'wpls_lead_log_sortable_columns' );

/**
 * Fügt Filter-Dropdown für 'lead_log' Log-Typ hinzu (Spez 3.5).
 */
function wpls_add_log_type_filter() {
    global $typenow;
    if ( $typenow == 'lead_log' ) {
        $selected = isset($_GET['log_type_filter']) ? sanitize_text_field($_GET['log_type_filter']) : '';
        $types = array(
            'email_sent' => 'E-Mail gesendet',
            'call_booked' => 'Call gebucht',
            'sequence_started' => 'Sequenz gestartet',
            'system_note' => 'System-Notiz',
        );
        
        echo '<select name="log_type_filter">';
        echo '<option value="">Alle Log-Typen</option>';
        foreach ($types as $key => $label) {
            echo '<option value="' . $key . '" ' . selected($selected, $key, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
}
add_action( 'restrict_manage_posts', 'wpls_add_log_type_filter' );

/**
 * Wendet den Log-Typ-Filter auf die Query an.
 */
function wpls_filter_logs_by_type( $query ) {
    global $pagenow;
    if ( is_admin() && $pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'lead_log' && isset($_GET['log_type_filter']) && !empty($_GET['log_type_filter']) ) {
        
        $query->set( 'meta_key', '_log_type' );
        $query->set( 'meta_value', sanitize_text_field($_GET['log_type_filter']) );
    }
}
add_action( 'parse_query', 'wpls_filter_logs_by_type' );
?>