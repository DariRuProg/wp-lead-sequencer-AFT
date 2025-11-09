WP Lead Sequencer: Technische API & Integrations-Dokumentation

Dieses Dokument beschreibt alle drei Methoden, um das Plugin mit externen Diensten wie Calendly, n8n oder Zapier zu verbinden.

Es gibt 3 Haupt-Integrationsmethoden:

Eingehende Webhooks (z.B. Calendly): Externe Dienste (wie Calendly) benachrichtigen das Plugin über ein Ereignis (z.B. "Call gebucht").

Plugin REST API (Eingehend): Externe Dienste (wie n8n) steuern das Plugin, um Aktionen auszuführen (z.B. "Lead erstellen", "Lead finden").

Ausgehende Webhooks (Ausgehend): Das Plugin benachrichtigt externe Dienste (wie n8n), wenn ein Ereignis eintritt (z.B. "Lead erstellt", "E-Mail gesendet").

1. Eingehende Webhooks (z.B. Calendly)

Diese Methode wird verwendet, damit Calendly Ihr Plugin benachrichtigen kann, wenn ein Termin gebucht wurde.

Ziel: Wenn ein Lead einen Termin bucht, wird sein Status im Plugin automatisch auf "Call gebucht" gesetzt und die E-Mail-Sequenz stoppt.

Einrichtung

Webhook-URL abrufen:

Gehen Sie in WordPress zu Lead Sequencer -> Einstellungen -> Integrationen (API/Webhooks).

Kopieren Sie die URL aus dem Feld "Ihre Webhook-URL" (schreibgeschützt).

Sie sieht so aus: https://deine-website.de/wp-json/lead-sequencer/v1/webhook/calendly

Calendly konfigurieren:

Loggen Sie sich in Calendly ein.

Gehen Sie zu "Integrations & apps" (im Hauptmenü, nicht zu "Personal access tokens").

Suchen Sie die App "Webhooks" und klicken Sie darauf.

Klicken Sie auf "Add Webhook".

Fügen Sie Ihre kopierte Webhook-URL (aus Schritt 1) in das Feld "Payload URL" ein.

Wählen Sie beim Event (Ereignis) "Invitee Created" (Eingeladener erstellt) aus.

Klicken Sie auf "Save".

Sicherheit einrichten (WICHTIG):

Nach dem Speichern zeigt Calendly den neuen Webhook in der Liste an. Klicken Sie darauf.

Suchen Sie nach dem "Signing Key" (ein langer geheimer Schlüssel). Kopieren Sie diesen Schlüssel.

Gehen Sie zurück zu Ihren WordPress-Einstellungen (... -> Integrationen).

Fügen Sie den kopierten "Signing Key" in das Feld "Calendly Signing Key" ein.

Speichern Sie die Einstellungen.

Funktionsweise:
Wenn nun ein Termin gebucht wird, sendet Calendly eine POST-Anfrage an Ihre URL. Ihr Plugin nutzt den "Signing Key", um zu verifizieren, dass die Anfrage echt ist. Es sucht dann den Lead anhand der E-Mail-Adresse und setzt dessen Status auf "booked".

2. Plugin REST API (Eingehend für n8n)

Diese Methode wird verwendet, damit Sie das Plugin von extern (z.B. n8n, Zapier oder einem eigenen Skript) fernsteuern können.

Ziel: Leads per API erstellen, finden oder aktualisieren.

Einrichtung

API-Schlüssel generieren:

Gehen Sie in WordPress zu Lead Sequencer -> Einstellungen -> Integrationen (API/Webhooks).

Erfinden Sie einen langen, sicheren Schlüssel (z.B. sk_live_dein-geheimer-key-123456...) und fügen Sie ihn in das Feld "Geheimer API-Schlüssel (Bearer Token)" ein.

Speichern Sie die Einstellungen.

n8n (oder anderen Dienst) konfigurieren:

Verwenden Sie einen "HTTP Request"-Node.

Stellen Sie sicher, dass Sie einen Authorization-Header senden.

Typ: Bearer Token

Wert: Der Schlüssel, den Sie in Schritt 1 erstellt haben.

Der Header muss so aussehen: Authorization: Bearer sk_live_dein-geheimer-key-123456...

Verfügbare Endpunkte

Alle Endpunkte benötigen den Authorization: Bearer ... Header.

POST /wp-json/lead-sequencer/v1/leads/create

Erstellt einen neuen Lead.

Body (JSON):

{
  "email": "max.mustermann@firma.de",
  "first_name": "Max",
  "last_name": "Mustermann",
  "company": "Firma GmbH",
  "role": "CEO",
  "status": "new"
}


Antwort (201 Created): Die vollständigen Daten des neu erstellten Leads.

GET /wp-json/lead-sequencer/v1/leads/find?email=...

Findet einen Lead anhand seiner E-Mail-Adresse.

URL-Parameter: ?email=max.mustermann@firma.de

Antwort (200 OK): Die vollständigen Daten des gefundenen Leads.

Antwort (404 Not Found): Wenn kein Lead mit dieser E-Mail gefunden wurde.

GET /wp-json/lead-sequencer/v1/leads/<id>

Ruft die Daten für einen einzelnen Lead anhand seiner WordPress Post-ID ab.

Beispiel: .../v1/leads/123

Antwort (200 OK): Die vollständigen Daten des Leads.

POST /wp-json/lead-sequencer/v1/leads/<id>

Aktualisiert einen bestehenden Lead.

Beispiel: .../v1/leads/123

Body (JSON): Senden Sie nur die Felder, die Sie ändern möchten.

{
  "_lead_status": "stopped",
  "_lead_company_name": "Neuer Firmenname"
}


Antwort (200 OK): Die aktualisierten vollständigen Daten des Leads.

3. Ausgehende Webhooks (Ausgehend an n8n)

Diese Methode wird verwendet, damit Ihr Plugin n8n (oder einen anderen Dienst) automatisch benachrichtigt, wenn im Plugin Aktionen stattfinden.

Ziel: n8n-Workflows starten, wenn ein Lead erstellt wird, eine E-Mail gesendet wird usw.

Einrichtung

n8n Webhook-URL abrufen:

Erstellen Sie einen neuen Workflow in n8n.

Fügen Sie einen "Webhook"-Trigger-Node hinzu.

Kopieren Sie die "Test URL" oder "Production URL" des Nodes.

Plugin konfigurieren:

Gehen Sie in WordPress zu Lead Sequencer -> Einstellungen -> Integrationen (API/Webhooks).

Scrollen Sie nach unten zu "Outbound Webhooks (Ausgehend an n8n, Zapier etc.)".

Fügen Sie Ihre n8n-Webhook-URL in das Feld für das gewünschte Ereignis ein (z.B. in "Lead Erstellt (Webhook-URL)").

Speichern Sie die Einstellungen.

Verfügbare Ereignisse

Das Plugin sendet die Daten als POST-Request mit JSON-Body.

Lead Erstellt (n8n_webhook_lead_created)

Wird ausgelöst, wenn ein Lead erstellt wird (via API, Import, AJAX-Formular).

Call Gebucht (n8n_webhook_lead_booked)

Wird ausgelöst, wenn der Calendly-Webhook erfolgreich verarbeitet wurde.

Sequenz Gestartet (n8n_webhook_lead_sequence_started)

Wird ausgelöst, wenn die Sequenz für einen Lead manuell (AJAX) oder per Bulk-Aktion gestartet wird.

E-Mail Gesendet (n8n_webhook_email_sent)

Wird bei jeder erfolgreich gesendeten E-Mail (Follow-up 1, 2, 3... oder No-Show) ausgelöst.

Beispiel-Payload (Was n8n empfängt):

{
  "event": "lead_created",
  "lead": {
    "id": 124,
    "name": "Max Mustermann",
    "date_created_gmt": "2025-11-09 18:30:00",
    "date_modified_gmt": "2025-11-09 18:30:00",
    "_lead_first_name": "Max",
    "_lead_last_name": "Mustermann",
    "_lead_contact_email": "max@firma.de",
    "_lead_role": "CEO",
    "_lead_company_name": "Firma GmbH",
    // ... alle anderen Lead-Felder ...
    "_lead_status": "new",
    "_lead_follow_ups_sent": "0"
  }
}
