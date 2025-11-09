/**
 * Frontend JavaScript für den [lead_manager_ui] Shortcode (Spez 4.1)
 */
document.addEventListener('DOMContentLoaded', function () {
    
    // Daten von wp_localize_script holen
    const leads = wpls_frontend_data.leads || [];
    const ajax_url = wpls_frontend_data.ajax_url;
    const security_nonce = wpls_frontend_data.security_nonce;

    const tableBody = document.getElementById('wpls-lead-table-body');
    const form = document.getElementById('wpls-new-lead-form');
    const formMessage = document.getElementById('wpls-form-message');

    // --- 1. Tabelle initial rendern (Spez 4.1) ---
    function renderTable() {
        if (!tableBody) return;
        tableBody.innerHTML = ''; // Tabelle leeren

        if (leads.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6">Noch keine Leads vorhanden.</td></tr>';
            return;
        }

        leads.forEach(lead => {
            const row = document.createElement('tr');
            
            // Status-Label
            const statusLabel = `<span class="wpls-status-label wpls-status-${lead.status}">${formatStatus(lead.status)}</span>`;
            
            // Aktionen
            let actions = '';
            // Aktion 1: Sequenz starten (Spez 4.1 Aktion 2)
            if (lead.status === 'new' || lead.status === 'stopped') {
                actions += `<button class="wpls-action-btn start wpls-start-seq" data-lead-id="${lead.id}">Sequenz starten</button>`;
            }
            // Aktion 2: No-Show markieren (Spez 4.1 Aktion 3)
            if (lead.status === 'booked' && lead.showed_call !== 'no' && lead.showed_call !== 'followed_up') {
                actions += `<button class="wpls-action-btn noshow wpls-mark-noshow" data-lead-id="${lead.id}">Als No-Show markieren</button>`;
            }
            if (actions === '') {
                actions = 'N/A';
            }

            row.innerHTML = `
                <td>${escapeHTML(lead.name)}</td>
                <td>${escapeHTML(lead.email)}</td>
                <td>${escapeHTML(lead.company)}</td>
                <td>${statusLabel}</td>
                <td>${lead.sent_count}</td>
                <td>${actions}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    // --- 2. Formular-Handler (Spez 4.1 Aktion 1) ---
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('wpls-add-lead-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Speichern...';
            showMessage(false);

            const formData = {
                action: 'add_new_lead',
                security: security_nonce,
                first_name: document.getElementById('wpls_first_name').value,
                last_name: document.getElementById('wpls_last_name').value,
                email: document.getElementById('wpls_contact_email').value,
                company: document.getElementById('wpls_company_name').value,
                role: document.getElementById('wpls_role').value,
            };

            // AJAX-Call (fetch)
            performAjaxRequest(formData)
                .then(response => {
                    showMessage(response.message, 'success');
                    // Realtime-Ersatz: Seite neu laden (Spez 4.1)
                    setTimeout(() => location.reload(), 1000); 
                })
                .catch(error => {
                    showMessage(error.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Lead hinzufügen';
                });
        });
    }

    // --- 3. Tabellen-Aktionen-Handler (Event Delegation) ---
    if (tableBody) {
        tableBody.addEventListener('click', function (e) {
            
            // Aktion: Sequenz starten (Spez 4.1 Aktion 2)
            if (e.target && e.target.classList.contains('wpls-start-seq')) {
                e.target.disabled = true;
                e.target.textContent = 'Starte...';
                
                performAjaxRequest({
                    action: 'start_lead_sequence',
                    security: security_nonce,
                    lead_id: e.target.dataset.leadId
                })
                .then(response => location.reload()) // Realtime-Ersatz
                .catch(error => {
                    alert('Fehler: ' + error.message);
                    e.target.disabled = false;
                    e.target.textContent = 'Sequenz starten';
                });
            }

            // Aktion: No-Show markieren (Spez 4.1 Aktion 3)
            if (e.target && e.target.classList.contains('wpls-mark-noshow')) {
                if (confirm('Sind Sie sicher, dass Sie diesen Lead als No-Show markieren möchten?\n\nEs wird eine No-Show-E-Mail ausgelöst (falls konfiguriert).')) {
                    e.target.disabled = true;
                    e.target.textContent = 'Markiere...';

                    performAjaxRequest({
                        action: 'mark_lead_noshow',
                        security: security_nonce,
                        lead_id: e.target.dataset.leadId
                    })
                    .then(response => location.reload()) // Realtime-Ersatz
                    .catch(error => {
                        alert('Fehler: ' + error.message);
                        e.target.disabled = false;
                        e.target.textContent = 'Als No-Show markieren';
                    });
                }
            }
        });
    }

    // --- Hilfsfunktionen ---

    // AJAX-Request-Funktion
    async function performAjaxRequest(data) {
        const params = new URLSearchParams();
        for (const key in data) {
            params.append(key, data[key]);
        }

        const response = await fetch(ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params
        });

        const result = await response.json();

        if (result.success) {
            return result.data;
        } else {
            throw new Error(result.data.message || 'Ein unbekannter Fehler ist aufgetreten.');
        }
    }
    
    // Status-Formatierung
    function formatStatus(status) {
        const labels = {
            'new': 'Neu',
            'sequencing': 'In Sequenz',
            'booked': 'Call gebucht',
            'stopped': 'Gestoppt',
        };
        return labels[status] || status;
    }
    
    // Formular-Nachricht
    function showMessage(message = false, type = 'success') {
        if (!formMessage) return;
        if (!message) {
            formMessage.style.display = 'none';
            return;
        }
        formMessage.textContent = message;
        formMessage.className = `wpls-message ${type}`;
        formMessage.style.display = 'block';
    }

    // HTML-Escape
    function escapeHTML(str) {
        return str.replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    // --- Initialer Aufruf ---
    renderTable();

});