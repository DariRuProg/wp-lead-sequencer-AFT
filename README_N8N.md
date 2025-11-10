n8n-Workflow: Calendly-Buchungen mit WP Lead Sequencer synchronisieren (Finale Version)

Dieser Workflow nutzt den "intelligenten" /leads/create-Endpunkt deines Plugins. Dieser Endpunkt führt automatisch eine "Finden-oder-Erstellen"-Aktion (Upsert) aus und reagiert auf das event-Feld.

Voraussetzung: Du hast deinen "Geheimen API-Schlüssel (Bearer Token)" aus Lead Sequencer -> Einstellungen -> Integrationen kopiert.

Dein n8n-Workflow (Nur 2 Nodes)

Node 1: Calendly Trigger

Diesen Node hast du bereits. Er liefert dir die JSON-Daten.

Node 2: HTTP Request (Lead "Upsert")

Dieser eine Node sendet die Daten an dein Plugin. Das Plugin kümmert sich um die gesamte Logik (Prüfen, ob der Lead existiert, dann aktualisieren oder neu erstellen).

Method: POST

URL: https://DEINE-WEBSITE.de/wp-json/lead-sequencer/v1/leads/create

Authentication: Header Auth

Name: Authorization

Value: Bearer DEIN-API-SCHLÜSSEL (Ersetze dies mit deinem Schlüssel)

Body Content Type: JSON

Body Parameters -> Add Parameter (füge alle 7 Parameter hinzu):

Key: email
Value: {{ $json.payload.email }}

Key: event (WICHTIG! Steuert die Logik)
Value: {{ $json.event }} (z.B. invitee.created or invitee.canceled)

Key: first_name
Value: {{ $json.payload.first_name || "" }}

Key: last_name
Value: {{ $json.payload.last_name || "" }}

Key: notes (Kunden-Notizen)
Value: {{ $json.payload.questions_and_answers[0].answer || "" }}

Key: event_type (Event-Name)
Value: {{ $json.payload.scheduled_event.name || "" }}

Key: time_call (Call-Zeit)
Value: {{ $json.payload.scheduled_event.start_time || "" }}

Was das Plugin jetzt tut

Wenn du diesen Node ausführst:

Das Plugin empfängt die Anfrage und prüft die E-Mail.

Wenn event = invitee.created:

Es sucht nach der E-Mail.

Existiert: Es aktualisiert den Lead, setzt den Status auf booked und stoppt die Sequenz. (Kein Duplikat)

Existiert nicht: Es erstellt einen neuen Lead direkt mit dem Status booked.

Wenn event = invitee.canceled:

Es sucht nach der E-Mail.

Existiert: Es aktualisiert den Lead, setzt den Status auf stopped (um die Sequenz zu stoppen) und setzt den "Call gebucht"-Status zurück.

Existiert nicht: Es passiert nichts (ein stornierter Call für einen unbekannten Lead wird ignoriert).