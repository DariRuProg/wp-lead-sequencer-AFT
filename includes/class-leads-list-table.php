<?php
/**
 * Erstellt die WP_List_Table für die Lead-Übersicht (Spez 3.1)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// WP_List_Table-Klasse laden, falls sie noch nicht verfügbar ist
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPLS_Leads_List_Table extends WP_List_Table {

    /**
     * Konstruktor. Setzt die Bezeichnungen.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Lead', 'wp-lead-sequencer' ),
            'plural'   => __( 'Leads', 'wp-lead-sequencer' ),
            'ajax'     => false // Keine AJAX-Paginierung
        ) );
    }

    /**
     * Holt die Daten für die Tabelle (Leads)
     * (Aktualisiert mit Suchfunktion - Aufgabe 3)
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Filter-Status holen
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        // NEU: Suchbegriff holen
        $search_term = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
    
        // WP_Query Argumente
        $args = array(
            'post_type'      => 'lead',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        
        $meta_query = array('relation' => 'AND');
    
        // Meta-Query für Status-Filter hinzufügen
        if ( ! empty($status_filter) ) {
            $meta_query[] = array(
                'key'     => '_lead_status',
                'value'   => $status_filter,
                'compare' => '=',
            );
        }
        
        // NEU: Suchlogik hinzufügen (Code aus Bericht 1:1 übernommen)
        if ( ! empty($search_term) ) {
            // WordPress durchsucht standardmäßig 'post_title'. Wir suchen auch in E-Mail.
            $args['s'] = $search_term; // Durchsucht post_title (Name)
            
            // Fügt E-Mail-Suche zur Meta-Query hinzu
            // HINWEIS: Dies führt zu (Titel/Name SUCHE) UND (E-Mail SUCHE)
            // Um (Titel ODER E-Mail) zu suchen, ist ein 'posts_clauses' Filter nötig.
            // Ich folge hier der Anweisung aus dem Bericht.
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_lead_contact_email',
                    'value'   => $search_term,
                    'compare' => 'LIKE',
                ),
            );
        }
        
        if ( count($meta_query) > 1 ) {
            $args['meta_query'] = $meta_query;
        }
    
    
        $query = new WP_Query( $args );
    
        $this->items = $query->posts;

        // Paginierungs-Argumente setzen
        $this->set_pagination_args( array(
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages
        ) );

        // Spalten-Header setzen
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
    }

    /**
     * Definiert die Spalten der Tabelle (Spez 3.1)
     */
    public function get_columns() {
        $columns = array(
            'cb'          => '<input type="checkbox" />',
            'name'        => __( 'Name', 'wp-lead-sequencer' ),
            'email'       => __( 'E-Mail', 'wp-lead-sequencer' ),
            'company'     => __( 'Firma', 'wp-lead-sequencer' ),
            'status'      => __( 'Status', 'wp-lead-sequencer' ),
            'follow_ups'  => __( 'Follow-ups', 'wp-lead-sequencer' ),
            'last_contact' => __( 'Letzter Kontakt', 'wp-lead-sequencer' ),
        );
        return $columns;
    }

    /**
     * Definiert die Checkbox-Spalte
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="lead_ids[]" value="%d" />', $item->ID
        );
    }

    /**
     * Definiert den Inhalt für jede Spalte (außer Standard-Spalten)
     */
    public function column_default( $item, $column_name ) {
        $lead_id = $item->ID;
        
        switch ( $column_name ) {
            case 'name':
                // Name (Titel) mit Bearbeiten-Link
                $edit_link = get_edit_post_link( $lead_id );
                $title = $item->post_title;
                $actions = array(
                    'edit' => '<a href="' . esc_url($edit_link) . '">' . __('Bearbeiten', 'wp-lead-sequencer') . '</a>',
                    'delete' => '<a href="' . get_delete_post_link($lead_id) . '" class="text-danger">' . __('Löschen', 'wp-lead-sequencer') . '</a>',
                );
                return '<strong>' . $title . '</strong>' . $this->row_actions( $actions );

            case 'email':
                return get_post_meta( $lead_id, '_lead_contact_email', true );
            case 'company':
                return get_post_meta( $lead_id, '_lead_company_name', true );
            case 'status':
                $status = get_post_meta( $lead_id, '_lead_status', true ) ?: 'new';
                return $this->format_status_label($status);
            case 'follow_ups':
                return (int) get_post_meta( $lead_id, '_lead_follow_ups_sent', true );
            case 'last_contact':
                $timestamp = get_post_meta( $lead_id, '_lead_sequence_last_date', true );
                return $timestamp ? date( 'Y-m-d H:i', $timestamp ) : 'N/A';
            default:
                return 'N/A';
        }
    }
    
    /**
     * Hilfsfunktion: Formatiert das Status-Label
     */
    private function format_status_label($status) {
        $labels = array(
            'new' => __('Neu', 'wp-lead-sequencer'),
            'sequencing' => __('In Sequenz', 'wp-lead-sequencer'),
            'booked' => __('Call gebucht', 'wp-lead-sequencer'),
            'stopped' => __('Gestoppt', 'wp-lead-sequencer'),
        );
        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Definiert die Bulk-Aktionen (Spez 3.1 / Anf. #4)
     */
    public function get_bulk_actions() {
        $actions = array(
            'bulk_start_sequence' => __( 'Sequenz(en) starten', 'wp-lead-sequencer' ),
            'bulk_delete'         => __( 'Löschen', 'wp-lead-sequencer' )
        );
        return $actions;
    }

    /**
     * Zeigt die Filter-Optionen über der Tabelle an (Spez 3.1)
     */
    public function extra_tablenav( $which ) {
        if ( $which == 'top' ) {
            $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
            ?>
            <div class="alignleft actions">
                <select name="status_filter">
                    <option value=""><?php _e('Alle Status', 'wp-lead-sequencer'); ?></option>
                    <option value="new" <?php selected($status_filter, 'new'); ?>><?php _e('Neu', 'wp-lead-sequencer'); ?></option>
                    <option value="sequencing" <?php selected($status_filter, 'sequencing'); ?>><?php _e('In Sequenz', 'wp-lead-sequencer'); ?></option>
                    <option value="booked" <?php selected($status_filter, 'booked'); ?>><?php _e('Call gebucht', 'wp-lead-sequencer'); ?></option>
                    <option value="stopped" <?php selected($status_filter, 'stopped'); ?>><?php _e('Gestoppt', 'wp-lead-sequencer'); ?></option>
                </select>
                <?php submit_button( __('Filtern', 'wp-lead-sequencer'), 'button', 'filter_action', false, array('id' => 'post-query-submit')); ?>
            </div>
            <?php
        }
    }
}
?>