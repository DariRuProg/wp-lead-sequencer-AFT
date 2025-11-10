<?php
/**
 * Verwaltet die Admin-Seiten und die Logik für CSV-Import und -Export.
 * (Aktualisiert mit Calendly-Feldern für den Export und englischen Labels)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Zeigt die Haupt-Importseite an (Callback von admin-menu.php)
 * Diese Funktion steuert, welcher Schritt des Imports angezeigt wird.
 */
function wpls_import_page_display() {
    echo '<div class="wrap">';
    echo '<h1>' . __( 'Lead-Import (CSV)', 'wp-lead-sequencer' ) . '</h1>';

    // Schritt 2: Mapping-Formular anzeigen, wenn eine Datei hochgeladen wurde
    if ( isset( $_POST['wpls_import_step'] ) && $_POST['wpls_import_step'] == '1' ) {
        
        // Nonce verifizieren
        if ( ! check_admin_referer( 'wpls-import-step-1' ) ) {
            wp_die( __( 'Sicherheitsprüfung fehlgeschlagen', 'wp-lead-sequencer' ) );
        }
        
        // Datei-Upload-Handling
        $upload_result = wpls_handle_csv_upload();
        
        if ( is_wp_error( $upload_result ) ) {
            // Fehler beim Upload anzeigen
            echo '<div classid="message error"><p>' . $upload_result->get_error_message() . '</p></div>';
            wpls_import_step_1_upload_form(); // Erneut Upload-Formular zeigen
        } else {
            // Upload erfolgreich, jetzt Mapping-Formular anzeigen
            wpls_import_step_2_mapping_form( $upload_result['file_path'], $upload_result['headers'] );
        }

    } 
    // Schritt 3: Den eigentlichen Import durchführen
    elseif ( isset( $_POST['wpls_import_step'] ) && $_POST['wpls_import_step'] == '2' ) {
        
        // Nonce verifizieren
        if ( ! check_admin_referer( 'wpls-import-step-2' ) ) {
            wp_die( __( 'Sicherheitsprüfung fehlgeschlagen', 'wp-lead-sequencer' ) );
        }

        // Führe den Import durch
        $file_path = sanitize_text_field( $_POST['wpls_csv_file_path'] );
        $column_map = (array) $_POST['wpls_column_map']; // Cast als Array
        
        $import_result = wpls_process_import( $file_path, $column_map );
        
        // Zeige das Ergebnis an
        wpls_import_step_3_results_display( $import_result );

    }
    // Schritt 1: Standard-Upload-Formular anzeigen
    else {
        wpls_import_step_1_upload_form();
    }

    echo '</div>';
}

/**
 * Zeigt das HTML für das CSV-Upload-Formular (Schritt 1)
 */
function wpls_import_step_1_upload_form() {
    ?>
    <p><?php _e( 'Laden Sie eine CSV-Datei hoch, um Ihre Leads zu importieren. Die erste Zeile der CSV muss die Spaltenüberschriften enthalten (z.B. "Vorname", "E-Mail").', 'wp-lead-sequencer' ); ?></p>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url( 'admin.php?page=wpls-import' ); ?>">
        <?php wp_nonce_field( 'wpls-import-step-1' ); ?>
        <input type="hidden" name="wpls_import_step" value="1" />
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="wpls_csv_file"><?php _e( 'CSV-Datei auswählen', 'wp-lead-sequencer' ); ?></label>
                </th>
                <td>
                    <input type="file" name="wpls_csv_file" id="wpls_csv_file" accept=".csv, text/csv" required />
                </td>
            </tr>
        </table>
        
        <?php submit_button( __( 'Datei hochladen & Mapping starten', 'wp-lead-sequencer' ) ); ?>
    </form>
    <?php
}

/**
 * Verarbeitet den Upload (Schritt 1)
 *
 * @return array|WP_Error Array mit Dateipfad und Headern bei Erfolg, sonst WP_Error.
 */
function wpls_handle_csv_upload() {
    if ( empty( $_FILES['wpls_csv_file'] ) || $_FILES['wpls_csv_file']['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', __( 'Fehler beim Datei-Upload. Bitte versuchen Sie es erneut.', 'wp-lead-sequencer' ) );
    }

    $file = $_FILES['wpls_csv_file'];
    
    // Dateityp prüfen (obwohl 'accept' im Formular hilft)
    $file_type = wp_check_filetype( $file['name'], ['csv' => 'text/csv'] );
    if ( $file_type['ext'] !== 'csv' ) {
        return new WP_Error( 'file_type_error', __( 'Ungültiger Dateityp. Bitte laden Sie eine .csv-Datei hoch.', 'wp-lead-sequencer' ) );
    }

    // Datei temporär verschieben (WP-Upload-Verzeichnis)
    $upload_dir = wp_upload_dir();
    $temp_file_path = wp_tempnam( $file['name'], $upload_dir['path'] . '/' );
    
    if ( ! move_uploaded_file( $file['tmp_name'], $temp_file_path ) ) {
        return new WP_Error( 'move_error', __( 'Datei konnte nicht in das Upload-Verzeichnis verschoben werden.', 'wp-lead-sequencer' ) );
    }

    // CSV öffnen und Header auslesen (fgetcsv)
    if ( ( $handle = fopen( $temp_file_path, 'r' ) ) === FALSE ) {
        return new WP_Error( 'read_error', __( 'Temporäre CSV-Datei konnte nicht gelesen werden.', 'wp-lead-sequencer' ) );
    }
    
    // UTF-8 BOM entfernen, falls vorhanden (verursacht oft Probleme beim ersten Header)
    $bom = "\xef\xbb\xbf";
    if ( fgets( $handle, 4 ) !== $bom ) {
        // Kein BOM, zurück an den Anfang
        rewind( $handle );
    }
    
    $headers = fgetcsv( $handle );
    fclose( $handle );
    
    if ( empty( $headers ) ) {
        unlink( $temp_file_path ); // Temporäre Datei löschen
        return new WP_Error( 'empty_csv', __( 'Die CSV-Datei ist leer oder die Header-Zeile konnte nicht gelesen werden.', 'wp-lead-sequencer' ) );
    }

    return array(
        'file_path' => $temp_file_path,
        'headers'   => $headers,
    );
}

/**
 * Zeigt das HTML für das Spalten-Mapping (Schritt 2)
 *
 * @param string $file_path Pfad zur temporären CSV-Datei.
 * @param array $csv_headers Die ausgelesenen Header aus der CSV.
 */
function wpls_import_step_2_mapping_form( $file_path, $csv_headers ) {
    
    // Alle verfügbaren Meta-Felder für Leads (Spez 2.1)
    $lead_meta_fields = wpls_get_lead_meta_fields();
    
    ?>
    <p><?php _e( 'Ihre Datei wurde hochgeladen. Bitte ordnen Sie nun die Spalten Ihrer CSV-Datei den entsprechenden Lead-Feldern im System zu.', 'wp-lead-sequencer' ); ?></p>
    <p><strong><?php _e( 'WICHTIG: Das Feld "E-Mail" muss einer Spalte mit gültigen E-Mail-Adressen zugewiesen werden, sonst wird die Zeile übersprungen.', 'wp-lead-sequencer' ); ?></strong></p>
    
    <form method="post" action="<?php echo admin_url( 'admin.php?page=wpls-import' ); ?>">
        <?php wp_nonce_field( 'wpls-import-step-2' ); ?>
        <input type="hidden" name="wpls_import_step" value="2" />
        <input type="hidden" name="wpls_csv_file_path" value="<?php echo esc_attr( $file_path ); ?>" />

        <h3><?php _e( 'Spalten-Mapping', 'wp-lead-sequencer' ); ?></h3>
        
        <table class="form-table">
            <thead>
                <tr>
                    <th><strong><?php _e( 'CSV-Spalte', 'wp-lead-sequencer' ); ?></strong></th>
                    <th><strong><?php _e( 'Zuordnen zu Feld...', 'wp-lead-sequencer' ); ?></strong></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $csv_headers as $index => $header ) : ?>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html( $header ); ?></th>
                    <td>
                        <select name="wpls_column_map[<?php echo esc_attr( $index ); ?>]">
                            <option value=""><?php _e( 'Nicht importieren', 'wp-lead-sequencer' ); ?></option>
                            <?php foreach ( $lead_meta_fields as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php submit_button( __( 'Import starten', 'wp-lead-sequencer' ) ); ?>
    </form>
    <?php
}

/**
 * Hilfsfunktion: Gibt alle Lead-Meta-Felder zurück (Spez 2.1)
 * (Aktualisiert, um post_title einzuschließen)
 *
 * @return array
 */
function wpls_get_lead_meta_fields() {
    return array(
        'post_title' => __( 'Name (Post-Titel)', 'wp-lead-sequencer' ), // NEU
        '_lead_first_name' => __( 'Vorname', 'wp-lead-sequencer' ),
        '_lead_last_name' => __( 'Nachname', 'wp-lead-sequencer' ),
        '_lead_contact_email' => __( 'E-Mail (Erforderlich)', 'wp-lead-sequencer' ),
        '_lead_role' => __( 'Rolle/Position', 'wp-lead-sequencer' ),
        '_lead_company_name' => __( 'Firmenname', 'wp-lead-sequencer' ),
        '_lead_company_industry' => __( 'Branche', 'wp-lead-sequencer' ),
        '_lead_company_address' => __( 'Adresse', 'wp-lead-sequencer' ),
        '_lead_contact_phone' => __( 'Telefon', 'wp-lead-sequencer' ),
        '_lead_website' => __( 'Webseite', 'wp-lead-sequencer' ),
    );
}

/**
 * Verarbeitet den eigentlichen Import (Schritt 3)
 * (Aktualisiert, um post_title zu verarbeiten)
 *
 * @param string $file_path Pfad zur temporären CSV-Datei.
 * @param array $column_map Das vom Benutzer definierte Spalten-Mapping.
 * @return array|WP_Error Zähler für importierte und übersprungene Zeilen oder WP_Error bei Fehler.
 */
function wpls_process_import( $file_path, $column_map ) {
    $imported_count = 0;
    $skipped_count = 0;

    // Sicherstellen, dass die Datei noch existiert
    if ( ! file_exists( $file_path ) ) {
        return new WP_Error( 'file_not_found', __( 'Importdatei nicht gefunden. Sie ist möglicherweise abgelaufen. Bitte starten Sie den Upload erneut.', 'wp-lead-sequencer' ) );
    }

    // CSV öffnen
    if ( ( $handle = fopen( $file_path, 'r' ) ) === FALSE ) {
        return new WP_Error( 'read_error', __( 'Temporäre CSV-Datei konnte nicht gelesen werden.', 'wp-lead-sequencer' ) );
    }

    // Header-Zeile überspringen
    // (BOM-Check erneut, falls die Seite neu geladen wurde - sicher ist sicher)
    $bom = "\xef\xbb\xbf";
    if ( fgets( $handle, 4 ) !== $bom ) {
        rewind( $handle );
    }
    fgetcsv( $handle );

    // Zeilen verarbeiten
    while ( ( $row = fgetcsv( $handle ) ) !== FALSE ) {
        
        $new_lead_data = array(
            'post_type'   => 'lead',
            'post_status' => 'publish',
        );
        $meta_input = array();
        
        $first_name = '';
        $last_name = '';
        $email = '';
        $post_title = ''; // NEU

        // Spalten-Mapping anwenden
        foreach ( $column_map as $csv_index => $meta_key ) {
            if ( ! empty( $meta_key ) ) {
                $value = isset( $row[$csv_index] ) ? $row[$csv_index] : '';
                
                // 'post_title' separat behandeln (ist kein Meta-Feld)
                if ( $meta_key == 'post_title' ) {
                    $post_title = sanitize_text_field( $value );
                    continue; 
                }

                // Sanitize
                if ( $meta_key == '_lead_contact_email' ) {
                    $sanitized_value = sanitize_email( $value );
                    $email = $sanitized_value;
                } elseif ( $meta_key == '_lead_website' ) {
                    $sanitized_value = esc_url_raw( $value );
                } else {
                    $sanitized_value = sanitize_text_field( $value );
                }

                $meta_input[$meta_key] = $sanitized_value;
                
                if ($meta_key == '_lead_first_name') $first_name = $sanitized_value;
                if ($meta_key == '_lead_last_name') $last_name = $sanitized_value;
            }
        }

        // WICHTIG: Einen Lead ohne E-Mail überspringen
        if ( empty( $email ) ) {
            $skipped_count++;
            continue;
        }

        // Post-Titel programmatisch füllen, ODER gemappten Titel verwenden
        if ( !empty( $post_title ) ) {
            $new_lead_data['post_title'] = $post_title;
        } elseif ( !empty($last_name) && !empty($first_name) ) {
            $new_lead_data['post_title'] = $last_name . ', ' . $first_name;
        } else {
            $new_lead_data['post_title'] = $email;
        }

        // Standard-Status-Felder setzen
        $meta_input['_lead_status'] = 'new';
        $meta_input['_lead_follow_ups_sent'] = 0;

        $new_lead_data['meta_input'] = $meta_input;

        // Lead erstellen
        $post_id = wp_insert_post( $new_lead_data );

        if ( ! is_wp_error( $post_id ) ) {
            $imported_count++;
            
            // n8n-Webhook auslösen
            wpls_send_outbound_webhook( 'n8n_webhook_lead_created', $post_id );
        } else {
            $skipped_count++;
        }
    }

    // Aufräumen
    fclose( $handle );
    unlink( $file_path ); // Temporäre Datei löschen

    return array(
        'imported' => $imported_count,
        'skipped'  => $skipped_count,
    );
}

/**
 * Zeigt die Ergebnisse des Imports an (Schritt 3)
 *
 * @param array|WP_Error $result Das Ergebnis von wpls_process_import.
 */
function wpls_import_step_3_results_display( $result ) {
    
    if ( is_wp_error( $result ) ) {
        echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>' . 
            sprintf(
                __( 'Import abgeschlossen! %d Leads wurden erfolgreich importiert. %d Zeilen wurden übersprungen (z.B. wegen fehlender E-Mail-Adresse).', 'wp-lead-sequencer' ),
                (int) $result['imported'],
                (int) $result['skipped']
            ) . 
        '</p></div>';
    }

    echo '<p><a href="' . admin_url( 'admin.php?page=wpls-import' ) . '" class="button-primary">' . __( 'Einen neuen Import starten', 'wp-lead-sequencer' ) . '</a></p>';
    echo '<p><a href="' . admin_url( 'admin.php?page=wpls-main-crm' ) . '" class="button-secondary">' . __( 'Zur Lead-Übersicht', 'wp-lead-sequencer' ) . '</a></p>';

}

//
// --- EXPORT-LOGIK (Anforderung #3) ---
//

/**
 * Zeigt die Export-Seite an (Schaltfläche)
 * (Callback von admin-menu.php)
 */
function wpls_export_page_display() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Lead-Export (CSV)', 'wp-lead-sequencer' ); ?></h1>
        <p><?php _e( 'Klicken Sie auf die Schaltfläche unten, um alle Leads (inklusive aller Meta-Felder) als CSV-Datei zu exportieren.', 'wp-lead-sequencer' ); ?></p>
        
        <form method="post" action="<?php echo admin_url( 'admin.php?page=wpls-export' ); ?>">
            <?php wp_nonce_field( 'wpls-export-action', 'wpls_export_nonce' ); ?>
            <input type="hidden" name="wpls_export_action" value="1" />
            <?php submit_button( __( 'Alle Leads als CSV exportieren', 'wp-lead-sequencer' ), 'primary' ); ?>
        </form>
    </div>
    <?php
}

/**
 * Hook: Prüft, ob ein Export ausgelöst wurde, und generiert die CSV.
 * Läuft über 'admin_init', um vor dem Senden von Headern aktiv zu werden.
 */
function wpls_trigger_csv_export() {
    
    // Prüfen, ob die Export-Aktion ausgelöst wurde
    if ( ! isset( $_POST['wpls_export_action'] ) || $_POST['wpls_export_action'] !== '1' ) {
        return;
    }

    // Nonce verifizieren
    if ( ! isset( $_POST['wpls_export_nonce'] ) || ! check_admin_referer( 'wpls-export-action', 'wpls_export_nonce' ) ) {
        wp_die( __( 'Sicherheitsprüfung fehlgeschlagen', 'wp-lead-sequencer' ) );
    }

    // Berechtigung prüfen
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Sie haben nicht die Berechtigung, diese Aktion auszuführen.', 'wp-lead-sequencer' ) );
    }

    // CSV-Generierung starten
    wpls_generate_lead_csv();
}
add_action( 'admin_init', 'wpls_trigger_csv_export' );

/**
 * Holt alle Leads, konvertiert sie in CSV und sendet sie an den Browser.
 */
function wpls_generate_lead_csv() {
    
    $filename = 'lead_export_' . date('Y-m-d_H-i-s') . '.csv';

    // CSV-Header setzen
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    // Schreib-Stream öffnen
    $output = fopen( 'php://output', 'w' );
    
    // UTF-8 BOM für Excel-Kompatibilität
    fputs( $output, "\xef\xbb\xbf" );

    // Alle Meta-Felder definieren (inkl. Tracking)
    $all_meta_fields = wpls_get_all_lead_meta_fields();
    
    // Header-Zeile für CSV (Post-ID und Post-Titel + alle Meta-Felder)
    $csv_headers = array( 'lead_id', 'post_title' );
    $csv_headers = array_merge( $csv_headers, array_keys($all_meta_fields) );
    fputcsv( $output, $csv_headers );

    // Alle Leads holen (effizient, ohne Paginierung)
    $args = array(
        'post_type' => 'lead',
        'post_status' => 'publish',
        'posts_per_page' => -1, // Alle
    );
    $leads_query = new WP_Query( $args );

    // Leads durchlaufen und CSV-Zeilen schreiben
    if ( $leads_query->have_posts() ) {
        while ( $leads_query->have_posts() ) {
            $leads_query->the_post();
            $post_id = get_the_ID();
            
            $row = array();
            $row[] = $post_id;
            $row[] = get_the_title();

            // Alle Meta-Felder für diesen Lead abrufen
            foreach ( $all_meta_fields as $meta_key => $label ) {
                $value = get_post_meta( $post_id, $meta_key, true );
                
                // Timestamp lesbar machen
                if ( $meta_key == '_lead_sequence_last_date' && !empty($value) ) {
                    $value = date( 'Y-m-d H:i:s', $value );
                }
                
                $row[] = $value;
            }
            
            fputcsv( $output, $row );
        }
    }
    wp_reset_postdata();

    // Stream schließen und Skript beenden
    fclose( $output );
    die();
}

/**
 * Hilfsfunktion: Gibt ALLE Lead-Meta-Felder zurück (für Export)
 * (Aktualisiert mit Calendly-Feldern und englischen Labels)
 *
 * @return array
 */
function wpls_get_all_lead_meta_fields() {
    // Startet mit den importierbaren Feldern
    $fields = wpls_get_lead_meta_fields();
    
    // post_title entfernen, da wir es separat als Hauptspalte (lead_id, post_title) behandeln
    if (isset($fields['post_title'])) {
        unset($fields['post_title']);
    }
    
    // Fügt die Tracking-Felder hinzu
    $fields['_lead_status'] = __( 'Lead-Status', 'wp-lead-sequencer' );
    $fields['_lead_started_sequence'] = __( 'Sequenz gestartet', 'wp-lead-sequencer' );
    $fields['_lead_sequence_last_date'] = __( 'Letztes Kontaktdatum', 'wp-lead-sequencer' );
    $fields['_lead_follow_ups_sent'] = __( 'Gesendete Follow-ups', 'wp-lead-sequencer' );
    $fields['_lead_call_scheduled'] = __( 'Call terminiert', 'wp-lead-sequencer' );
    $fields['_lead_showed_call'] = __( 'Call No-Show Status', 'wp-lead-sequencer' );
    $fields['_lead_is_incomplete'] = __( 'Unvollständig', 'wp-lead-sequencer' );
    
    // Fügt die NEUEN Calendly-Felder hinzu (mit englischen Keys für den Export)
    $fields['_lead_calendly_event_name'] = __( 'Calendly Event Name', 'wp-lead-sequencer' );
    $fields['_lead_calendly_start_time'] = __( 'Calendly Call Time', 'wp-lead-sequencer' );
    $fields['_lead_calendly_notes'] = __( 'Calendly Notes', 'wp-lead-sequencer' );
    
    return $fields;
}
?>