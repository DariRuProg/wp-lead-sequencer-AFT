/**
 * AJAX-Logik für die Admin-CRM-Tabelle (z.B. "No-Show"-Dropdowns)
 */
document.addEventListener('DOMContentLoaded', function () {
    
    // Wir verwenden Event-Delegation, um auf alle Dropdowns in der Tabelle zu hören
    const table = document.getElementById('leads-filter'); 
    if (!table) {
        return;
    }

    table.addEventListener('change', function (e) {
        // Prüfen, ob es eines unserer Status-Dropdowns ist
        if (e.target.classList.contains('wpls-call-status-select')) {
            const select = e.target;
            const leadId = select.dataset.leadId;
            const newValue = select.value;
            const row = select.closest('tr');
            
            // Visuelles Feedback geben
            select.disabled = true;
            if (row) {
                row.style.opacity = '0.5';
            }

            // AJAX-Daten vorbereiten
            const data = new URLSearchParams();
            data.append('action', 'wpls_update_call_status');
            data.append('security', wpls_admin_crm_ajax.nonce);
            data.append('lead_id', leadId);
            data.append('call_status', newValue);

            // AJAX-Aufruf
            fetch(wpls_admin_crm_ajax.ajax_url, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                // Feedback wieder entfernen
                select.disabled = false;
                if (row) {
                    row.style.opacity = '1';
                }

                if (result.success) {
                    // Erfolg! Zeige eine grüne Benachrichtigung (wird in admin-styles.css definiert)
                    wpls_show_admin_notice(result.data.message, 'success', row);
                    
                    // Dropdown auf den neuen Wert einfärben
                    select.classList.remove('wpls-status-yes', 'wpls-status-no', 'wpls-status-booked');
                    if (newValue === 'yes') {
                        select.classList.add('wpls-status-yes');
                    } else if (newValue === 'no') {
                        select.classList.add('wpls-status-no');
                    } else {
                        select.classList.add('wpls-status-booked');
                    }
                    
                } else {
                    // Fehler
                    wpls_show_admin_notice('Fehler: ' + result.data.message, 'error', row);
                }
            })
            .catch(error => {
                select.disabled = false;
                if (row) {
                    row.style.opacity = '1';
                }
                wpls_show_admin_notice('Netzwerk-Fehler: ' + error.message, 'error', row);
            });
        }
    });

    /**
     * Zeigt eine temporäre Benachrichtigung unter der Tabellenzeile an
     */
    function wpls_show_admin_notice(message, type = 'success', row) {
        // Alte Nachrichten entfernen
        const oldNotice = row.querySelector('.wpls-ajax-notice');
        if (oldNotice) {
            oldNotice.remove();
        }

        const notice = document.createElement('div');
        notice.className = `wpls-ajax-notice wpls-ajax-${type}`;
        notice.textContent = message;
        
        // Füge es in der ersten Zelle der NÄCHSTEN Zeile hinzu (oder erstelle eine neue)
        // Einfacher: Fügen wir es einfach in der 'status'-Zelle ein
        const cell = row.querySelector('.column-status');
        if (cell) {
            cell.appendChild(notice);
            
            setTimeout(() => {
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 500);
            }, 3000);
        }
    }

});