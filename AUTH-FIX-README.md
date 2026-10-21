# FredKI CourseImport 1.6.7-rc2 – Authentifizierung/Wiederverbindung (Testversion)

Basis: `mod_fredkicourseimport_1.6.6-rc1_Moodle_5.2_OK.zip`.

## Nachgewiesener Fehler

- `token_manager::DEFAULT_LIFETIME = 28800`: nach acht Stunden gilt der gespeicherte Token unabhängig von seiner tatsächlichen serverseitigen Lebensdauer als abgelaufen.
- Die alte Implementierung löscht abgelaufene Datensätze und besitzt keine automatischen Neuanmeldedaten.
- Sobald die Zuordnung zu einer Wissensbasis feststeht, werden die Loginfelder im Bearbeitungsformular ausgeblendet. Ein erneutes Anmelden war deshalb blockiert.
- Auf unerwartete HTTP-401-Antworten reagierte der Client ohne Token-Erneuerung.
- Fehlgeschlagene Synchronisationen konnten bei unverändertem Quellinhalt im Status `error` verbleiben.

Diese Schwächen finden sich teilweise schon in der älteren Version `1.4.0-rc2`; das ältere Verhalten allein beweist daher keine stabile Token-Erneuerung.

## Korrekturen

1. Auf der Seite der Aktivität erscheint bei abgelaufener Autorisierung ein Wiederverbindungsformular. Bearbeiter können die Verbindung auch jederzeit über **„Verbindung jederzeit neu autorisieren“** öffnen.
2. Die bestehende Wissensbasis-ID und die importierten Dokumentzuordnungen werden bei der Wiederanmeldung **nicht** geändert. Vor Übernahme wird die Sichtbarkeit der zugeordneten Wissensbasis mit dem neuen Konto geprüft.
3. Die Option **„Zugang verschlüsselt speichern und automatisch erneuern“** ist standardmäßig **deaktiviert**. Nach ausdrücklicher Einwilligung verwendet sie `core\encryption` und einen lokalen Moodle-Schlüssel; bei der manuellen Wiederanmeldung ohne Haken wird die vorherige Speicherung dieser Zugangsdaten aufgehoben.
4. Automatische Anmeldung fünf Minuten vor dem lokal angenommenen Ablauf und einmaliger Retry nach einer HTTP-401-Antwort, einschließlich JSON-, Upload- und Löschoperationen. Andere API-Fehler werden nicht als Tokenablauf fehlinterpretiert.
5. Bei fehlgeschlagener Erneuerung ist eine manuelle Wiederverbindung möglich; der Token gilt lokal als abgelaufen. Der Fehler wird nicht durch eine willkürliche Verlängerung kaschiert.
6. Quellen im Status `error` werden bei unverändertem Inhalt beim nächsten Synchronisierungslauf erneut versucht.
7. Neuer optionaler Datenbankwert `fredkicourseimport_auth.passwordencrypted`; Upgrade ohne Löschen der Wissensbasis-ID, Inhaltsauswahl oder Dokumentzuordnung.

## Installation/Test

1. Vorher Datenbank, Moodledata und den bestehenden Pluginstand sichern.
2. In einer Moodle-5.2-Testinstallation ZIP über die Moodle-Plugininstallation aktualisieren; das Datenbank-Upgrade ausführen lassen.
3. Betroffene Aktivität öffnen, erneut mit FredKI-Benutzername und Kennwort anmelden. Automatische Erneuerung nur dann aktivieren, wenn die Speicherung des verschlüsselten Kennworts gewünscht ist.
4. Sicherstellen, dass dieselbe Wissensbasis-ID angezeigt wird, vorhandene Inhalte nicht gelöscht wurden und die Synchronisierung funktioniert.
5. Die lokale Gültigkeitszeit in einer Testumgebung verkürzen oder den Token gezielt ungültig machen; automatische Erneuerung und HTTP-401-Fall an der echten FredKI-API prüfen.
6. Bei geändertem Kennwort die manuelle Wiederverbindung testen. Verweigert FredKI das Konto dauerhaft oder fällt die API aus, kann das Plugin die Verbindung nicht ohne gültige Zugangsdaten wiederherstellen.
7. Moodle-Fehlerprotokoll prüfen, aber **keine** Passwörter, Tokens oder komplette HTTP-Header in Tickets/Screenshots weitergeben.

**Achtung:** Der Moodle-Verschlüsselungsschlüssel in `moodledata` ist für die Wiederherstellung verschlüsselter Zugangsdaten erforderlich. Bei Verlust müssen die Zugangsdaten erneut eingegeben werden. Bei einem Datenbankkompromiss allein sind die Kennwörter nicht im Klartext lesbar, bei einer vollständigen Serverkompromittierung besteht dennoch Risiko.

## Prüfstand / Grenzen

Lokal durchgeführt: PHP-Syntaxprüfung, XMLDB-XML-Parsing, statische Konsistenzprüfung und 21 simulierte Anmeldung-/Ablauf-/HTTP401-/Konkurrenz-/Verschlüsselungstests.

Noch nicht durchgeführt: vollständige Moodle-PHPUnit- und Browserintegrationstests in Moodle 5.2, echte FredKI-/MaxKB-API-Endpunkttests, ein achtstündiger Langzeittest sowie Prüfungen einer Server-/Datenbank-Wiederherstellung. Daher nur als **Security/Auth-Preview** auf einer Testinstallation verwenden.

Die separate frühere **Moodle-Berechtigungs-Security-Preview 02a** ist bewusst **noch nicht mit diesem Auth-Fix zusammengeführt**. Für das spätere FredKI-Gesamtpaket müssen beide Zweige zusammengeführt und erneut getestet werden.


## rc12: Automatic connection without repeated manual sign-in

- The authorising editor enables encrypted password retention **once** when signing in.
- That preference is retained for the activity and its current authorising Moodle user.
- When enabled, the stored (encrypted) credentials are used to refresh expiring FredKI
  tokens. A rejected token also triggers one re-authentication attempt.
- The activity now clearly displays the automatic renewal status and does not imply
  that manual re-authorisation is routinely necessary.
- Re-opening the credentials form preserves the checked state *only if the opt-in
  already exists*. New opt-ins remain unchecked until intentionally selected.
- No password is ever prefilled into HTML, and the linked knowledge base remains
  unchanged. No credential is written to Moodle course backups.
- A changed/disabled FredKI account, server outage or unavailable Moodle encryption
  key can still require manual intervention. Protect and back up Moodle encryption keys.
