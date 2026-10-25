# 1.7.1 (2026-10-25) – Korrekturen

- **JavaScript:** Source-Maps für `amd/build` ergänzt. Ohne sie lädt Moodle im Entwicklermodus (JS-Cache aus) die ES-Quelldateien aus `amd/src`, die der Browser nicht ausführen kann. Dadurch funktionierten Wissensbasis-Auswahl und Inhaltsauswahl nicht.
- **Inhaltsauswahl:** Neue, noch nie gespeicherte Aktivitäten folgen der Auswahl „Für Teilnehmende sichtbar“. Die hervorgehobene Auswahl entspricht jetzt immer dem tatsächlichen Zustand der Häkchen (bei individueller Auswahl ist keine hervorgehoben).
- **Formular:** „Gespeicherte FredKI-Anmeldung verwenden“ ist beim ersten Aufruf nicht mehr vorausgewählt.
- **Sprache:** Deutsche Sprachdatei (`lang/de`) wieder enthalten.

---

# 1.7.0 (2026-10-24) – Stabile Version

- **Reife:** Von Release Candidate (`MATURITY_RC`) auf stabil (`MATURITY_STABLE`) umgestellt; funktional identisch mit 1.7.0-rc7.
- **Upgrade:** Reihenfolge der Upgrade-Savepoints korrigiert (`2026042810` → `20260428010`).
- **Sprachdateien:** Doppelt definierten String `configureactivityfirst` entfernt (der wirksame, spätere Text bleibt unverändert).
- **Marketplace:** Deutsche Code-Kommentare übersetzt, README auf Englisch neu geschrieben.
- **Allgemein ausgerichtet:** Ungenutzte, themenspezifische Hilfsfunktionen in `sync_manager` entfernt (feste Kategorien und Schlagwörter wie „Vereinsgründung und Vereinsrecht“ oder „Lesestunden gestalten“); keine Auswirkung auf den Import.
- **Dokumentation:** Fehlende `@param`/`@return`-Angaben in `classes/local` ergänzt; Moodle-CI (Codestil, Savepoints, PHPDoc, PHPUnit) läuft fehlerfrei.

---

# 1.7.0-rc7 (2026-10-24) – Marketplace-Vorbereitung

- **JavaScript:** Inline-Skripte und `selection.js` durch AMD-Module (`amd/src/knowledgebases.js`, `amd/src/selection.js`) ersetzt, geladen über `js_call_amd`. Die Dateien in `amd/build` sind von Hand im Moodle-Format erzeugt; vor einer Einreichung einmal `grunt amd` ausführen.
- **Blöcke:** Der FredKI-Agent-Block wird über die Moodle-Block-API angelegt und gelöscht (kein direkter Zugriff auf `block_instances`, kein `unserialize`).
- **Code:** Moodle-Codestil (phpcs) ohne Fehler, Docblocks und Klassenbeschreibungen vollständig, deaktivierten Agenten-Formularteil entfernt, leere `catch`-Blöcke protokollieren im Entwicklermodus, `require_login` in `index.php`.
- **Tests:** Datenschutz-Test für `get_metadata()` ergänzt.

---

# 1.7.0-rc6 (2026-10-24) – Code-Bereinigung

- **Dokumentation:** PHPDoc-Kommentare für alle Funktionen und Klassen ergänzt.
- **Stil:** Zeilen über 132 Zeichen großteils umgebrochen (Token-Vergleich vor/nach dem Umbruch identisch). Keine funktionalen Änderungen.

---

# 1.7.0-rc5 (2026-10-24) – Anmeldung merken und wiederverwenden

- **Neu:** Kästchen „FredKI-Anmeldung merken“ (Passwort verschlüsselt über Moodle `core\encryption`, Token wird automatisch erneuert) und beim Anlegen weiterer Aktivitäten „Gespeicherte FredKI-Anmeldung verwenden“.
- **Datenbank:** TEXT-Spalten werden mit `sql_isnotempty()` geprüft; das Lesen der gespeicherten Anmeldung ist gegen Fehler abgesichert.

---

# 1.7.0-rc4 (2026-10-24) – GPL v3

- **Lizenz:** GNU GPL v3 oder später; Moodle-GPL-Kopf in allen Dateien, proprietäre Lizenzdatei entfernt.
- **Lizenzschlüssel entfernt:** Die externe Lizenzprüfung entfällt; ein Upgrade-Schritt löscht alte Lizenz-Einstellungen.
- **Code:** `defined('MOODLE_INTERNAL')` in allen Dateien ergänzt. Sonst funktional identisch mit 1.6.7-rc2.

---

# 1.6.7-rc2 (2026-10-13) – Stabilitätskorrekturen für Moodle 5.2

- **Backup/Löschen:** Die Backup- und Restore-Aufgaben laden ihre Stepslib-Dateien jetzt ausdrücklich; behebt `Class "backup_fredkicourseimport_activity_structure_step" not found`.
- **Zugang:** Bisher wurden temporäre Zugangstokens nach acht Stunden gelöscht. Diese Version erhält die Authentifizierungsdaten und erneuert das Token über verschlüsselt gespeicherte Zugangsdaten (Moodle `core\encryption`).
- **Kursaktivität:** Bereits ausgewählte Wissensbasis-ID, Kurszuordnungen und synchronisierte Dokument-IDs bleiben bei regulären Plugin-Updates erhalten. Wenn ein Alt-Token schon abgelaufen ist, lassen sich Zugangsdaten in **derselben** Aktivität einmalig neu eingeben.
- **Vorabprüfung:** Synchronisierung meldet erneute Anmeldung als Fehler, *bevor* Dateien oder Mapping-Status verändert werden, falls FredKI vorübergehend nicht erreichbar ist.
- **Darstellung:** Das Öffnen der Kursaktivität führt nicht für jeden Seitenaufruf eine Netzwerkanmeldung aus.
- **Bestehende Agentenfelder:** Werden beim Bearbeiten nicht durch deaktivierte Formularelemente geleert.
- **Datenschutz:** Passwörter und Tokens sind nicht Bestandteil von Kurs-Backups oder Datenschutzexporten; verschlüsselte Passwortaufbewahrung ist dokumentiert.
- **Hinweis:** Einmalige Neuanmeldung nach Upgrade von älteren Versionen erforderlich, weil die früheren Passwörter nicht rekonstruierbar sind. Keine Neuinstallation/Deinstallation. Realer Moodle-5.2-End-to-End-Test erforderlich.

---

# 1.6.6-rc1 – Moodle 5.2 test candidate (2026-10-11)

- Approved FredKI Suite activity icons applied (`pix/icon.*`, `pix/monologo.svg`).
- Adds a Moodle 5.2 course activity overview redirect via `index.php`.
- Declares the content/resource activity purpose.
- Serialises import and refresh actions with the Moodle Lock API. Saving a new
  source selection, loading the saved selection, generating files and syncing
  are now protected by the same activity-scoped lock.
- Requires POST, a session key and `mod/fredkicourseimport:sync` for changes.
  Restricts the import status overview to authorised course staff.
- On deleting the *entire activity*, preserves external document identifiers
  without tokens in an administrator-only review register (`cleanup.php`). It
  **does not** automatically delete the corresponding FredKI documents.
- Extends privacy export and erasure handling for `authorizedbyuserid`.
- Adds a database upgrade for the cleanup register.
- **Test candidate:** Moodle 5.2 and live FredKI integration testing pending;
  current proprietary plugin licence still needs Marketplace review.

# 1.6.4

- Vollständig/Referenz-Synchronisation auf den bewährten 1.6.x-Pfad konsolidiert.
- Bei `Vollständig` wird der extrahierte Dokumentinhalt tatsächlich als durchsuchbarer Wissensinhalt übertragen; `Referenz` überträgt weiterhin nur Metadaten und Moodle-Link.
- Robuste Verarbeitung unterschiedlicher FredKI-Split-Antwortformen sowie PDF-Chunking bleibt aktiv.
- Moodle-Custom-Fields bleiben sowohl als echte FredKI-Tags als auch ohne zusätzliche „FredKI-Tags“-Überschrift im durchsuchbaren Wissensinhalt erhalten.
- Keine automatisch erzeugten verknüpften Fragen (`problem_list`).

# 1.6.3

- Entfernt die automatische Erzeugung aller MaxKB-verknüpften Fragen (`paragraph.problem_list`).
- Importierte Abschnitte enthalten nur noch den eigentlichen suchbaren Inhalt: Titel, Custom Fields/Tags, Beschreibung, Abschnittshierarchie, Links und ggf. Dokumentinhalt.
- Bereits synchronisierte aktive Inhalte werden nach dem Upgrade einmal neu erstellt, damit die zuvor vom Importer erzeugten Fragen zusammen mit den alten FredKI-Dokumenten verschwinden.
- `prepare_searchable_paragraphs()` entfernt vorsorglich auch ein eventuell bereits vorhandenes `problem_list` aus Split-Antworten.

# 1.6.2 – Präzisere Suche / keine generischen Doppel-Fragen

- Entfernt generische, bei vielen Dokumenten identische MaxKB-Verknüpfungsfragen wie `Welche Buchtipps gibt es?`, `Welche Bücher werden empfohlen?`, allgemeine Zielgruppenfragen sowie Materialtyp-/Themen-/Abschnittsfragen.
- Pro Material bleibt nur die titelbezogene Frage `Wo finde ich "<Titel>"?` erhalten.
- `Materialtyp`, `Zielgruppe`, `Thema`, Abschnittshierarchie und Beschreibung bleiben vollständig im durchsuchbaren Abschnittstext und als echte FredKI-Tags erhalten.
- Dadurch werden unterschiedliche Materialien in Hybrid-/Vektorsuche nicht mehr durch identische `problem_list`-Embeddings künstlich ähnlich.
- Nach dem Upgrade werden bestehende ausgewählte Inhalte einmal neu synchronisiert, damit alte generische verknüpfte Fragen in FredKI ersetzt werden. Danach arbeitet die selektive Aktualisierung wieder nur bei tatsächlichen Änderungen.

# 1.6.1 – Abschnittshierarchie / Moodle-Unterabschnitte

- Überträgt bei Moodle-4.5+-Unterabschnitten neben dem direkten Unterabschnitt auch die übergeordnete Abschnittsüberschrift in die FredKI-Wissensbasis.
- Darstellung bei Unterabschnitten: `Übergeordneter Abschnitt: ...` und `Unterabschnitt: ...`; normale Hauptabschnitte bleiben als `Abschnitt: ...` erhalten.
- Unterstützt rekursiv mehrere Ebenen, falls Moodle bzw. ein Kursformat verschachtelte delegierte `mod_subsection`-Abschnitte liefert.
- Verknüpfte Suchfragen verwenden den vollständigen Abschnittskontext, damit Begriffe aus Ober- und Unterüberschriften in MaxKB besser auffindbar sind.
- Änderungen an einer übergeordneten oder untergeordneten Überschrift werden vom selektiven Aktualisieren erkannt. Nach dem Upgrade werden bestehende ausgewählte Inhalte einmal neu synchronisiert, um die Hierarchie zu übernehmen.

# 1.6.0 – Search-oriented FredKI import

- Uses the real Moodle activity/material title as the MaxKB paragraph title instead of the generic `Basisinformationen`.
- Places Moodle custom-field tags such as `Materialtyp`, `Zielgruppe` and `Thema` directly below the title.
- Removes duplicated technical metadata (`Quelle in Moodle`, `Kursbereich`, repeated activity name) while retaining the direct activity link, course link, section and useful file/type information.
- Adds up to four deterministic MaxKB linked questions per document via `paragraph.problem_list`; book materials automatically receive questions such as `Welche Buchtipps gibt es?`.
- Existing synchronized material is refreshed once after upgrading so the new search structure and linked questions are applied. Subsequent updates remain selective.

# 1.5.2

- Moodle custom-field tags are now also written into the searchable `Basisinformationen` block.
- Real FredKI tags continue to be created and assigned unchanged.
- Human-readable custom-field names are used as metadata labels, e.g. `Materialtyp: Buch`.

# 1.5.1 (stable)

- Referenz ist die Standardoption für alle neuen Uploads.
- Moodle-Custom-Fields werden als FredKI-Tags übertragen.
- Aktivitätsbeschreibungen werden in Referenz-Uploads vollständig übernommen und nicht mehr nach 280 Zeichen gekürzt.
- Bestehende explizite Auswahl von Vollständig/Referenz bleibt erhalten.

# 1.5.1-beta1-tags (2026-09-25)

## 1.5.1-beta3-reference-default-all-uploads

- Reference is now the global default for every new upload path and mapping when no explicit mode is supplied.
- The database default for new mapping records is Reference.
- Explicitly selected or already stored Complete modes are preserved.


- Restored transfer of Moodle activity custom fields from `local_modcustomfields` to FredKI.
- Non-empty activity custom fields are created/reused as real FredKI knowledge-base tags (`shortname` = key, exported value = value).
- Tags are assigned to each newly imported FredKI document before an older replacement document is deleted.
- Tag sync failures keep the previous document intact and clean up the newly created untagged document when possible.
- The integration remains optional: if `local_modcustomfields` is not installed, imports continue without tags.

## 1.4.3.4
- Prevented successful synchronous transfers from remaining visually stuck on Processing.
- Old FredKI document cleanup is now best-effort and uses a short dedicated timeout.
- Successful HTTP 204/empty DELETE responses are accepted.
- Restored pages reset the upload control when loaded from the browser back/forward cache.

# 1.4.3.3

- Added a local PDF text extraction fallback using `pdftotext` or `mutool` when FredKI rejects an otherwise valid text PDF.
- The upload indicator now switches from Ready to Processing immediately when the synchronization form is submitted.
- The submit button is locked during processing to prevent duplicate synchronization requests.

## 1.4.3-rc1

- Added Complete / Reference selection to every supported Moodle activity.
- Complete glossary exports now include all approved glossary entries.
- Complete mode is now preserved for generated page, label, glossary, book and lesson documents instead of being forced to Reference.
- Reference mode continues to export only activity metadata and the Moodle link.

# Changes

## 1.4.2-rc9
- Fixed the course-content selection page by making Moodle's global `$PAGE` object available inside the renderer before registering `selection.js`.

## 1.4.2-rc7

- Fixed Complete mode so extracted PDF, DOC and DOCX content is included after the Moodle metadata.
- Reference mode continues to transfer metadata and Moodle links only.
- Propagated the persisted mapping content mode into the synchronisation manager.

# 1.4.2-rc6

- Bulk selection links Visible / All / None now work server-side without JavaScript.
- Selection remains reliable under restrictive Content Security Policy settings.

## 1.4.2-rc4

- Aligned file/folder rows into fixed columns for document title, activity type, and Complete/Reference selection.
- Renamed the English knowledge mode label to Complete.
- Kept responsive wrapping for narrow screens.

## 1.4.2-rc2

- Removed visual parentheses around the Knowledge source / Reference controls.
- Positioned the controls immediately after the File or Folder type label on desktop layouts.

# Changes

## 1.4.1-rc2

- Added the missing `licenseconfigurefirst` language string in German and English.

# Change log

## 1.4.1-rc1

- Rebased the security and privacy improvements on the proven synchronous workflow.
- Added explicit synchronization capability.
- Enforced POST and plugin-specific course capability checks on compatibility AJAX endpoints.
- Sanitized developer debugging so external response bodies are not exposed.
- Kept passwords transient and user-scoped tokens out of backups and exports.
- Retained Backup/Restore, Privacy API, Moodle HTTP wrapper, and Moodle 4.5 minimum version.


## 1.4.2-rc1

- Added per-file choice between Knowledge source and Reference in the content-selection view.
- Knowledge source transfers the original file content; Reference transfers metadata and the Moodle link only.
- FredKI document type prefixes now follow the configured course language, with the site language as fallback.
- Added persistent `contentmode` metadata to synchronization mappings and backup/restore.

## 1.4.3.2
- More robust PDF/Office upload with detected MIME type and safe filename retries.
- Reference mode no longer uploads the original binary file for extraction.
- Specific non-sensitive extraction error messages.
- Existing FredKI documents are only deleted after successful replacement.

## 1.5.0-rc1

- Complete PDF imports are split into bounded semantic paragraphs instead of one oversized block.
- Large PDFs are uploaded to FredKI in safe text parts and combined into one FredKI document.
- PDF paragraph and heading boundaries are retained for better retrieval.
- Reference mode and non-PDF import paths remain unchanged.
