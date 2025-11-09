WP Lead Sequencer - Dokumentation

Willkommen beim WP Lead Sequencer Plugin. Dieses Plugin bietet ein leichtgewichtiges CRM innerhalb von WordPress, um Leads zu verwalten und E-Mail-Follow-up-Sequenzen automatisch zu versenden.

WICHTIG: Ersteinrichtung (E-Mail-Versand)

Dieses Plugin nutzt die native WordPress-Funktion wp_mail() für den E-Mail-Versand.

Damit der E-Mail-Versand funktioniert, MÜSSEN Sie ein separates SMTP-Plugin installieren und konfigurieren (z.B. "WP Mail SMTP", "FluentSMTP" oder ein ähnliches).

Das WP Lead Sequencer Plugin übergibt die E-Mails an dieses SMTP-Plugin, das sich dann um den zuverlässigen Versand über Ihren E-Mail-Anbieter (Gmail, Outlook, SendGrid etc.) kümmert.

Installation

Laden Sie den gesamten Ordner wp-lead-sequencer in Ihr /wp-content/plugins/-Verzeichnis hoch.

Gehen Sie zum "Plugins"-Menü in Ihrem WordPress-Admin-Bereich.

Suchen Sie "WP Lead Sequencer" und klicken Sie auf "Aktivieren".

Nach der Aktivierung finden Sie das neue Admin-Menü "Lead Sequencer" in Ihrer Seitenleiste.

Features & Funktionsweise

Hier ist eine Aufschlüsselung, wie die einzelnen Teile des Plugins funktionieren.

1. Das Admin-Menü "Lead Sequencer"

Nach der Aktivierung finden Sie die folgenden Seiten im Admin-Menü:

Übersicht (CRM): Die Hauptansicht zur Verwaltung Ihrer Leads (siehe Punkt 6).

E-Mail-Vorlagen: Hier erstellen Sie Ihre Follow-up-E-Mails (siehe Punkt 2).

Lead-Import: Ein CSV-Importer (siehe Punkt 4).

Lead-Export: Ein CSV-Exporter (siehe Punkt 5).

Statistiken / Logs: Ein Protokoll aller Aktionen im System.

Einstellungen: Konfiguration der Absender- und Sequenz-Logik (siehe Punkt 3).

2. E-Mail-Vorlagen (Spez 2.2)

Das Herzstück Ihrer Automatisierung.

Gehen Sie zu "Lead Sequencer" -> "E-Mail-Vorlagen".

Klicken Sie auf "Neue Vorlage hinzufügen".

Titel: Geben Sie einen Titel ein (z.B. "Follow Up 1 - Erste Mail").

Editor: Schreiben Sie den Inhalt Ihrer E-Mail (HTML/Text) in den WordPress-Editor.

Platzhalter: Sie können die folgenden Platzhalter verwenden, die automatisch ersetzt werden:

[FIRST_NAME]

[LAST_NAME]

[EMAIL]

[COMPANY]

[ROLE]

Vorlagen-Einstellungen (Metabox):

E-Mail-Betreff: Geben Sie die Betreffzeile Ihrer E-Mail ein.

Vorlagen-Typ (Zweck): Dies ist ENTSCHEIDEND. Wählen Sie aus, wofür diese E-Mail verwendet wird:

Follow Up 1: Die erste E-Mail, die sofort gesendet wird, wenn eine Sequenz startet.

Follow Up 2-5: Die automatisierten Follow-ups, die per Cron-Job gesendet werden.

No-Show E-Mail: Wird gesendet, wenn ein Lead als "No-Show" markiert wird.

3. Einstellungen (Spez 3.6)

Gehen Sie zu "Lead Sequencer" -> "Einstellungen".

Tab "Allgemein":

Absender-Name: Der "Von"-Name, den Ihre Leads sehen.

Absender-E-Mail: Die "Von"-E-Mail-Adresse. (Diese sollte in Ihrem SMTP-Plugin autorisiert sein).

Tab "Sequenz-Logik":

Maximale Follow-ups: Wie viele E-Mails (z.B. 3) soll eine Sequenz maximal senden?

Tage zwischen Follow-ups: Wie viele Tage soll das System warten, bevor es die nächste E-Mail sendet (z.B. 3 Tage).

4. Lead-Import (CSV) (Spez 3.3)

Importieren Sie Leads stapelweise.

Schritt 1: Upload: Laden Sie Ihre CSV-Datei hoch. Die erste Zeile MUSS die Spaltenüberschriften enthalten.

Schritt 2: Mapping: Ordnen Sie die Spalten Ihrer CSV-Datei den Feldern im System zu (z.B. "E-Mail-Adresse" -> "E-Mail").

Schritt 3: Import: Der Import läuft. Zeilen ohne E-Mail-Adresse werden übersprungen.

5. Lead-Export (CSV) (Spez 3.4)

Gehen Sie zu "Lead Sequencer" -> "Lead-Export". Ein Klick auf den Button exportiert alle Leads und alle zugehörigen Daten (inklusive Status, gesendete E-Mails etc.) als CSV-Datei.

6. Admin-CRM (Übersicht) (Spez 3.1)

Die Hauptseite ("Übersicht (CRM)") ist eine angepasste Tabelle, die Ihnen den Status all Ihrer Leads anzeigt.

Filter: Sie können Leads nach ihrem Status filtern (Neu, In Sequenz, Call gebucht, Gestoppt).

Bulk-Aktionen (Anforderung #4): Sie können mehrere Leads auswählen und:

Sequenz(en) starten: Startet die Sequenz (sendet Follow Up 1) für alle ausgewählten Leads.

Löschen: Verschiebt die Leads in den Papierkorb.

7. Frontend-UI (Shortcode) (Spez 4.0)

Sie können das CRM auch auf einer beliebigen Seite im Frontend anzeigen (nur für eingeloggte Administratoren sichtbar).

Shortcode: [lead_manager_ui]

Fügen Sie diesen Shortcode zu einer beliebigen Seite oder einem Beitrag hinzu.

Funktionen (Spez 4.1):

Schnell-Hinzufügen-Formular: Fügen Sie schnell neue Leads hinzu.

Übersichtstabelle: Zeigt alle Leads an.

Aktionen:

"Sequenz starten" (für 'Neue' Leads).

"Als No-Show markieren" (für 'Call gebucht' Leads).

8. Automatisierung (WP-Cron) (Spez 5.2 & 5.3)

Die Automatisierung läuft über zwei WP-Cron-Jobs:

Follow-up-Prüfung (Alle 15 Min):

Sucht Leads mit Status "In Sequenz", bei denen kein Call gebucht ist.

Prüft, ob Tage zwischen Follow-ups (aus Einstellungen) vergangen sind.

Prüft, ob Maximale Follow-ups (aus Einstellungen) noch nicht erreicht ist.

Falls ja: Sendet die nächste E-Mail (z.B. "Follow Up 3").

Falls Max. erreicht: Setzt Status auf "Gestoppt".

No-Show-Prüfung (Alle 30 Min):

Sucht Leads mit Status _lead_showed_call = 'no' (markiert über UI/Admin).

Sendet die "No-Show E-Mail"-Vorlage.

Setzt den Status auf followed_up, um Doppel-Versand zu verhindern.

9. REST-API (Webhooks) (Spez 6.0)

Das Plugin stellt zwei Endpunkte bereit, um mit externen Diensten zu kommunizieren:

Endpunkt 1 (Calendly): POST /wp-json/lead-sequencer/v1/webhook/calendly

Dieser Endpunkt ist öffentlich. Er sucht den Lead anhand der E-Mail im Payload (z.B. von Calendly) und setzt dessen Status auf "Call gebucht" (_lead_call_scheduled = 1 und _lead_status = 'booked').

Endpunkt 2 (Lead Erstellen): POST /wp-json/lead-sequencer/v1/lead/create

Erstellt einen neuen Lead.

Sicherheit: Dieser Endpunkt erfordert eine Authentifizierung (der aufrufende Benutzer muss manage_options Rechte haben, z.B. über die WordPress REST-API-Authentifizierung).