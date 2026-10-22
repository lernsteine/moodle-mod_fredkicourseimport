<?php
// This file is part of FredKI CourseImport for Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */











$string['pluginname'] = 'FredKI-Kursdaten';
$string['modulename'] = 'FredKI-Kursdaten';
$string['modulenameplural'] = 'FredKI-Kursdaten';
$string['pluginadministration'] = 'FredKI-Kursdaten verwalten';
$string['modulename_help'] = 'Übernimmt vorhandene Kursinhalte direkt nach FredKI.';
$string['knowledgeid'] = 'Wissensbasis ID';
$string['knowledgebasename'] = 'Wissensbasis Name';
$string['files'] = 'Erzeugte Importdateien';
$string['nofiles'] = 'Noch keine Kursdaten übernommen.';
$string['syncstatus'] = 'Synchronisationsstatus';
$string['nosyncentries'] = 'Noch keine Einträge in der Sync-Tabelle.';
$string['fredkidocumentid'] = 'FredKI Dokument-ID';
$string['fredkicourseimport:view'] = 'FredKI-Kursimport anzeigen';
$string['lastsync'] = 'Letzte Synchronisation';
$string['baseurl'] = 'FredKI-Basis-URL';
$string['loginpath'] = 'FredKI-Login-Pfad';
$string['debuglog'] = 'Debug-Logging aktivieren';
$string['status_pending'] = 'ausstehend';
$string['status_synced'] = 'synchronisiert';
$string['knowledgebase'] = 'Wissensbasis';
$string['status_deleted'] = 'gelöscht';
$string['status_error'] = 'Fehler';
$string['fredkisettingsheader'] = 'FredKI-Zugang und Wissensbasis';
$string['licensekey'] = 'Lizenzschlüssel';
$string['licensekey_desc'] = 'Geben Sie den Lizenzschlüssel für diese Moodle-Website ein.';
$string['invalidlicense'] = 'Dieses Plugin ist für diese Moodle-Website nicht lizenziert.';
$string['fredkiusername'] = 'FredKI-Benutzername';
$string['fredkipassword'] = 'FredKI-Kennwort';
$string['availableknowledgebases'] = 'Verfügbar:';
$string['chooseknowledgebase'] = 'Bitte Wissensbasis auswählen';
$string['loadknowledgebases'] = 'Wissensbasis laden';
$string['knowledgeidrequired'] = 'Bitte eine Wissensbasis auswählen.';
$string['configureactivityfirst'] = 'Bitte zuerst in den Einstellungen dieser Aktivität FredKI-Benutzername, Kennwort und Wissensbasis speichern.';
$string['activitymappinglockedinfo'] = 'Die Zuordnung wurde bereits eingerichtet und kann nicht mehr geändert werden. Erstellen Sie bei Bedarf die Aktivität neu.';
$string['authorizedby'] = 'Autorisiert durch';
$string['importallcoursecontent'] = 'Alle Kursdaten zu FredKI';
$string['importdone'] = 'Import abgeschlossen. Neu: {$a->created}, aktualisiert: {$a->updated}, unverändert: {$a->skipped}, gelöscht: {$a->deleted}. Insgesamt verarbeitet: {$a->files} Dateien, {$a->sectiontexts} Abschnittstexte, {$a->activitytexts} Aktivitätsbeschreibungen, {$a->urls} Links, {$a->glossaries} Glossarbeiträge, {$a->pages} Seiten, {$a->labels} Textfelder, {$a->books} Buchkapitel, {$a->lessons} Lektionen, {$a->h5p} H5P-Guides, {$a->scorm} SCORM-Guides.';
$string['entercredentialstoload'] = 'Bitte zuerst Benutzername und Kennwort eingeben.';
$string['knowledgebasesloading'] = 'Wissensbasen werden geladen ...';
$string['knowledgebasesloaderror'] = 'Fehler beim Laden der Wissensbasen.';
$string['knowledgebasesloaded'] = 'Wissensbasen geladen.';
$string['missingbaseurl'] = 'Die FredKI-Basis-URL ist nicht konfiguriert.';
$string['filenotfound'] = 'Datei in Moodle nicht gefunden.';
$string['invalidlicensemissing'] = 'Es wurde noch kein Lizenzschlüssel für FredKI-Kursdaten hinterlegt.';
$string['invalidlicensedomain'] = 'Die Moodle-Domain konnte nicht ermittelt werden.';
$string['invalidlicenseserverempty'] = 'Der Lizenzserver hat nicht geantwortet.';
$string['invalidlicenseserverresponse'] = 'Der Lizenzserver hat keine gültige Antwort geliefert.';
$string['license_required_for_activity'] = 'Bitte hinterlegen Sie zuerst in den Plugin-Einstellungen einen gültigen Lizenzschlüssel. Danach können Wissensbasen geladen und Aktivitäten genutzt werden.';
$string['connectionoverview'] = 'Verbindung und Ziel';
$string['generateddocumentscount'] = 'Erzeugte Dokumente';
$string['synceddocumentscount'] = 'Synchronisiert';
$string['pendingdocumentscount'] = 'Ausstehend';
$string['errordocumentscount'] = 'Fehler';
$string['generatedfilepath'] = 'Bereich';
$string['generatedfilename'] = 'Datei';
$string['generatedfilesize'] = 'Größe';
$string['glossaryname'] = 'Glossar';
$string['glossarydefinition'] = 'Definition';
$string['glossaryentries'] = 'Glossarbeiträge';
$string['untitledglossaryentry'] = 'Unbenannter Glossarbeitrag';
$string['sourcelabel'] = 'Textfeld';
$string['processedcontentcount'] = 'Verarbeitete Inhalte';
$string['createddocumentscount'] = 'Neu erstellt';
$string['updateddocumentscount'] = 'Aktualisiert';
$string['skippeddocumentscount'] = 'Unverändert';
$string['deleteddocumentscount'] = 'Gelöscht';
$string['status_removed'] = 'gelöscht';
$string['syncprogress'] = 'Synchronisationsstand';
$string['syncrunning'] = 'Import läuft gerade. Bitte Seite nicht schließen.';
$string['syncrunningstep'] = 'Kursdaten werden gesammelt und zu FredKI übertragen ...';
$string['progressstarting'] = 'Import wird vorbereitet ...';
$string['licenseblockmessage'] = 'Die FredKI-Funktionen sind gesperrt, weil keine gültige Lizenz vorliegt.';
$string['choosecontentforimport'] = 'Kursinhalte auswählen und zu FredKI übertragen';
$string['selectcontentintro'] = 'Wählen Sie aus, welche Kursinhalte zu FredKI übertragen werden sollen. Wenn Sie nichts auswählen, werden alle bisherigen Inhalte dieser Aktivität bei der nächsten Synchronisation aus der FredKI-Wissensbasis entfernt.';
$string['startselectedimport'] = 'Ausgewählte Inhalte zu FredKI übertragen';
$string['coursesectiontexts'] = 'Abschnittstexte des Kurses';
$string['nothingselected'] = 'Keine Inhalte ausgewählt. Bestehende Inhalte werden aus der FredKI-Wissensbasis entfernt.';
$string['ready'] = 'Bereit';
$string['processingupload'] = 'Wird verarbeitet …';
$string['selectvisible'] = 'Sichtbar für Teilnehmer/innen';
$string['selection'] = 'Auswahl';
$string['hiddenforparticipants'] = 'Für Teilnehmer/innen aktuell verborgen';
$string['selectall'] = 'Alle';
$string['deselectall'] = 'Keine';
$string['fredkiagentheader'] = 'FredKI-Agent';
$string['agentid'] = 'FredKI Agent';
$string['agentid_help'] = 'Wählen Sie hier den FredKI-Agenten aus. Die Agenten werden mit den hinterlegten FredKI-Zugangsdaten geladen.';
$string['fredkiembedcode'] = 'FredKI Embed-Code';
$string['fredkiembedcode_help'] = 'Fügen Sie hier den FredKI-Einbettungscode ein, der mit <script async defer src="https://fredki.lernsteine.com/chat/api/embed?..."> beginnt. Das Plugin gibt nur FredKI-Embed-Scripte von der konfigurierten FredKI-Domain aus.';
$string['chooseagent'] = 'Bitte Agent auswählen';
$string['availableagents'] = 'Verfügbare Agenten';
$string['loadagents'] = 'Agenten laden';
$string['agentsloading'] = 'Agenten werden geladen ...';
$string['agentsloaderror'] = 'Fehler beim Laden der Agenten.';
$string['agentsloaded'] = 'Agenten geladen.';
$string['fredkiembedauto'] = 'Der Embed-Code wird automatisch aus dem ausgewählten Agenten erzeugt. Das Feld bleibt intern und muss nicht manuell bearbeitet werden.';
$string['agentcoursevisible'] = 'Chatbot auf der Kursseite anzeigen';
$string['agentcoursevisible_help'] = 'Wenn aktiviert, wird das FredKI-Embed-Script auf der Kursseite ausgegeben. Dadurch erscheint der ausgewählte Agent direkt im Kurs.';
$string['fredkichaturl'] = 'FredKI Chat-URL';
$string['fredkichaturl_help'] = 'Fügen Sie hier die öffentliche Chat-URL des Agenten ein, z. B. https://fredkitest.lernsteine.com/chat/ff19702075bfdb0b. Daraus erzeugt das Plugin automatisch den Embed-Code.';
$string['fredkichaturl_desc'] = 'Öffentliche Chat-URL des ausgewählten FredKI-Agenten. Wenn FredKI die URL beim Laden des Agenten nicht mitliefert, kann sie hier manuell eingefügt werden.';
$string['selectedknowledgebase'] = 'Ausgewählte Wissensbasis:';
$string['noknowledgebasechosen'] = 'Noch keine Wissensbasis ausgewählt.';
$string['selectknowledgebasebutton'] = 'Auswählen';
$string['selectedknowledgebasebutton'] = 'Ausgewählt';
$string['defaultactivityname'] = 'Kursinhalte zu FredKI übertragen';
$string['includeactivitycontent'] = 'Inhalt der Aktivität übertragen';
$string['fredkicourseimport:manage'] = 'FredKI-Kursimport verwalten';
$string['invalidcoursecontext'] = 'Der Kurskontext konnte nicht ermittelt werden.';
$string['invalidjsonrequest'] = 'Die Anfrage konnte nicht verarbeitet werden.';
$string['privacy:metadata:fredkicourseimport'] = 'Konfigurationsdaten der FredKI-Kursimport-Aktivität.';
$string['privacy:metadata:fredki'] = 'FredKI ist ein externes System, an das ausgewählte Moodle-Kursdaten übertragen werden.';
$string['privacy:metadata:fredki:username'] = 'Der FredKI-Benutzername zur Authentifizierung.';
$string['privacy:metadata:fredki:password'] = 'Das FredKI-Kennwort zur Authentifizierung.';
$string['privacy:metadata:fredki:coursecontent'] = 'Die vom berechtigten Nutzer ausgewählten Kursinformationen.';
$string['fredkicourseimport:addinstance'] = 'FredKI-Kursimport hinzufügen';
$string['externalapierror'] = 'Der FredKI-Dienst konnte die Anfrage nicht ausführen.';
$string['externalapiinvalidresponse'] = 'Der FredKI-Dienst hat eine ungültige Antwort geliefert.';
$string['missingvalidtoken'] = 'Die FredKI-Verbindung ist abgelaufen oder wurde abgelehnt. Bitte öffnen Sie die Einstellungen der CourseImport-Aktivität und autorisieren Sie die Verbindung dort erneut. Die Wissensbasis bleibt erhalten.';
$string['invalidrequestmethod'] = 'Diese Anfragemethode ist nicht erlaubt.';
$string['privacy:metadata:auth'] = 'Nutzerbezogene FredKI-Autorisierungsdaten.';
$string['privacy:metadata:auth:userid'] = 'Die Moodle-Nutzer-ID.';
$string['privacy:metadata:auth:username'] = 'Der FredKI-Benutzername.';
$string['privacy:metadata:auth:token'] = 'Das geheime FredKI-Zugriffstoken.';
$string['privacy:metadata:auth:tokenexpires'] = 'Der lokale Ablaufzeitpunkt des Tokens.';
$string['privacy:metadata:auth:lastused'] = 'Der Zeitpunkt der letzten Tokenverwendung.';
$string['privacy:metadata:fredki:browserdata'] = 'Browser-Verbindungsdaten, die beim Laden der optionalen FredKI-Chat-Einbettung übertragen werden.';

$string['fredkicourseimport:sync'] = 'FredKI-Kursimport synchronisieren';

$string['invalidbaseurl'] = 'Die FredKI-Basis-URL muss eine gültige HTTPS-Adresse sein.';
$string['licenseconfigurefirst'] = 'Konfigurieren und prüfen Sie zuerst die FredKI-Lizenz in den Plugin-Einstellungen, bevor Sie die FredKI-Funktionen verwenden.';


$string['contentmodeknowledge'] = 'Vollständig';
$string['contentmodereference'] = 'Referenz';
$string['contentmode_help'] = 'Vollständig überträgt alle Inhalte, die Moodle für die Aktivität bereitstellt. Bei einem Glossar wird jeder freigegebene Eintrag einzeln übertragen – mit eigener Überschrift, Definition und Direktlink zum Moodle-Eintrag. Referenz überträgt nur den Glossartitel, die Beschreibung und den Link zur Glossarübersicht.';
$string['importrunning'] = 'Import läuft ...';
$string['documenttype_material'] = 'Material';
$string['documenttype_section'] = 'Kursabschnitt';
$string['documenttype_resource'] = 'Datei';
$string['documenttype_folder'] = 'Verzeichnis';
$string['documenttype_url'] = 'Link';
$string['documenttype_glossary'] = 'Glossar';
$string['documenttype_page'] = 'Seite';
$string['documenttype_label'] = 'Text- und Medienfeld';
$string['documenttype_book'] = 'Buch';
$string['documenttype_lesson'] = 'Lektion';
$string['documenttype_h5p'] = 'H5P';
$string['documenttype_scorm'] = 'SCORM';

$string['applytoselected'] = 'Für ausgewählte Dateien';
$string['apply'] = 'Übernehmen';

$string['documentextractfailed'] = 'Der Dokumentinhalt konnte von FredKI nicht extrahiert werden. Die Datei ist möglicherweise verschlüsselt, beschädigt, nur bildbasiert, zu groß oder verwendet eine nicht unterstützte PDF-/Office-Variante.';
$string['updatechanges'] = 'Änderungen aktualisieren';
$string['updatechangesonly'] = 'nur Änderungen aktualisieren';
$string['refreshdone'] = 'Änderungen aktualisiert. Neu: {$a->created}, aktualisiert: {$a->updated}, unverändert: {$a->skipped}, gelöscht: {$a->deleted}.';

// Moodle 5.2 suite audit, version 1.6.6-rc1.
$string['syncalreadyrunning'] = 'Für diese Aktivität läuft bereits ein FredKI-Import. Bitte versuche es nach dessen Abschluss erneut.';
$string['cleanupregister'] = 'Externe FredKI-Dokumente – Bereinigungsregister';
$string['cleanupregisteropen'] = 'Gespeicherte Dokumentkennungen nach dem Löschen einer Courseimport-Aktivität prüfen';
$string['cleanupregisterwarning'] = 'Diese Liste belegt nicht, dass die externen Dokumente noch existieren. Bitte vor jeder Löschung in FredKI prüfen. Die Einträge werden NICHT automatisch gelöscht. Hier werden keine Passwörter oder Zugangstokens gespeichert. Angezeigt werden höchstens 1.000 aktuelle Einträge.';
$string['cleanupcourseid'] = 'Ursprüngliche Moodle-Kurs-ID';
$string['cleanupworkspace'] = 'FredKI-Workspace';
$string['cleanupknowledgeid'] = 'Wissensbasis-ID';
$string['cleanupdocumentid'] = 'Externe Dokument-ID';
$string['cleanupregistered'] = 'Erfasst am';
$string['cleanupempty'] = 'Keine externen Dokumentkennungen aus gelöschten Aktivitäten vorhanden.';
$string['privacy:metadata:activity'] = 'Zugeordnete importberechtigte Person';
$string['privacy:metadata:activity:authorizedbyuserid'] = 'Moodle-Nutzer-ID des ursprünglich für den FredKI-Import autorisierten Trainers.';
$string['reauthheading'] = 'FredKI-Verbindung erneuern';
$string['reauthhelp'] = 'Bitte melden Sie sich erneut an. Die bestehende Wissensbasis und alle importierten Dokumente bleiben erhalten.';
$string['reauthremember'] = 'Nach ausdrücklicher Zustimmung Zugang verschlüsselt speichern und abgelaufene Tokens automatisch erneuern (Moodle-Server-Schlüssel sichern).';
$string['reauthbutton'] = 'Verbindung erneuern';
$string['reauthsuccess'] = 'FredKI-Verbindung erfolgreich erneuert.';
$string['reauthmissingcredentials'] = 'Benutzername und Passwort müssen eingegeben werden.';
$string['reauthknowledgeinaccessible'] = 'Das angegebene FredKI-Konto hat keinen Zugriff auf die zugeordnete Wissensbasis. Es wurde nichts verändert.';
$string['authrenewbusy'] = 'FredKI-Autorisierung wird gerade durch einen anderen Prozess erneuert. Bitte erneut versuchen.';
$string['reauthlink'] = 'Verbindung jederzeit neu autorisieren';
$string['reauthwarning'] = 'Die FredKI-Verbindung ist abgelaufen oder wurde abgelehnt. Öffnen Sie die Einstellungen dieser CourseImport-Aktivität, um den FredKI-Zugang neu zu autorisieren. Die bestehende Wissensbasis bleibt erhalten.';

$string['invalidglossaryentry'] = 'Der Glossareintrag ist nicht mehr freigegeben oder kann nicht eindeutig zugeordnet werden. Bitte die Quellen erneut synchronisieren.';

$string['confirmunselect'] = 'Ich bestätige, dass abgewählte Inhalte bei der nächsten Synchronisierung aus der FredKI-Wissensbasis entfernt werden.';
$string['confirmunselectrequired'] = 'Sie haben bereits freigegebene Inhalte abgewählt. Bitte bestätigen Sie die Entfernung ausdrücklich unter der Auswahl.';
$string['deletionbatchblocked'] = 'Sicherheitsstopp: {$a} Löschungen stehen zur Prüfung an. Es wurden nicht alle Dokumente gelöscht. Bitte wenden Sie sich an die Administration.';
$string['taskreconciledeleted'] = 'FredKI: in Moodle tatsächlich gelöschte Quellen abgleichen';

$string['privacy:metadata:auth:passwordencrypted'] = 'Optional verschlüsselt gespeichertes Kennwort für die ausdrücklich aktivierte automatische Wiederanmeldung.';

// Explicit, scoped confirmation for manual deselection (rc11).
$string['deselectionreviewheading'] = 'Entfernung ausgewählter Inhalte bestätigen';
$string['deselectionreviewintro'] = 'Sie haben zuvor freigegebene Kursinhalte abgewählt. Es wurde noch nichts synchronisiert oder gelöscht. Kontrollieren Sie diese Auswahl: Nur die nachfolgend genannten Quellen werden aus der FredKI-Wissensbasis entfernt.';
$string['deselectionremotecount'] = 'Derzeit zu diesen Quellen zugeordnete FredKI-Dokumente: {$a}. Die Anzahl kann bei Glossaren mit vielen Einträgen größer als die Zahl der ausgewählten Moodle-Aktivitäten sein.';
$string['deselectionconfirmbutton'] = 'Entfernung bestätigen und synchronisieren';
$string['deselectionreviewhint'] = 'Beim Abwählen bereits freigegebener Inhalte wird vor der Synchronisierung eine separate Bestätigung mit den betroffenen Quellen und Dokumentzahlen angezeigt.';
$string['deselectionapprovalexpired'] = 'Die Auswahl oder der Dokumentbestand hat sich seit der Bestätigung geändert. Bitte prüfen und bestätigen Sie die aktualisierte Liste erneut.';
$string['missingmoodlesource'] = 'Nicht mehr vorhandene Kursaktivität (keine manuelle Löschung durch diese Auswahl)';
$string['autorenewactive'] = 'Automatische FredKI-Wiederverbindung ist aktiv. Das Plugin erneuert den Zugangstoken bei Bedarf im Hintergrund. Keine regelmäßige manuelle Anmeldung nötig.';
$string['autorenewinactive'] = 'Automatische Wiederverbindung ist noch nicht aktiviert. Bitte einmalig anmelden und die verschlüsselte Speicherung ausdrücklich erlauben; danach werden ablaufende Tokens automatisch erneuert.';
$string['reauthenable'] = 'Automatische Wiederverbindung einmalig einrichten';
$string['reauthmanage'] = 'FredKI-Zugang ändern oder automatische Wiederverbindung verwalten';
$string['autorenewretained'] = 'Die automatische Wiederverbindung war für diesen Zugang bereits aktiviert. Sie bleibt aktiviert, solange dieses Kontrollkästchen ausgewählt bleibt.';

$string['reauthsettingslabel'] = 'FredKI-Zugang';
$string['reauthsettingslink'] = 'Zugang neu autorisieren oder automatische Wiederverbindung verwalten';

$string['reauthsettingssummary'] = 'Zugangsdaten verwalten';
$string['reauthajaxfailed'] = 'Die Verbindung konnte nicht erneuert werden. Bitte Zugangsdaten und FredKI-Erreichbarkeit prüfen.';

$string['privacy:metadata:entitlement'] = 'Eine gesonderte FredKI-Lizenzschnittstelle auf bildungbleibt.de prüft den Dienstzugang.';
$string['privacy:metadata:entitlement:licensekey'] = 'Der konfigurierte FredKI-Dienstlizenzschlüssel.';
$string['privacy:metadata:entitlement:domain'] = 'Der Hostname der Moodle-Website ohne Schema und Pfad.';
$string['privacy:metadata:entitlement:plugin'] = 'Die feste Plugin-Kennung mod_fredkicourseimport.';
