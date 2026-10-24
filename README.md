# FredKI-Kursdaten

Moodle activity module for synchronising selected Moodle course content with a FredKI knowledge base and optionally displaying a FredKI chatbot on the course page.

## Main features

- FredKI login inside the activity settings.
- Knowledge base selection from FredKI.
- Agent selection from FredKI.
- Course-content synchronisation into the selected knowledge base.
- Optional chatbot display on the course page.

## Installation

Copy the plugin directory to:

```text
mod/fredkicourseimport
```

Then run the Moodle upgrade process from Site administration.

## Licensing

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License v3 or (at your option) any later version, as published by the Free Software Foundation. No license key is required. The FredKI service the plugin connects to is a separate service with its own terms.

## Notes

The chatbot is displayed through the public FredKI embed script. Agent configuration itself remains in FredKI.


## Security and privacy

AJAX configuration endpoints require valid Moodle login, session keys and appropriate
capabilities. The import actions require `mod/fredkicourseimport:sync` and POST with a
valid session key. FredKI login passwords are not stored in activity records. Since 1.6.7-rc2,
renewal credentials are stored *encrypted* via Moodle core\encryption in the
`fredkicourseimport_auth` table, alongside a user-bound temporary access token. The plugin declares its stored and externally
transmitted data through the Moodle Privacy API. Debug logging should not remain
enabled on production sites.

## Backup and restore

The plugin implements Moodle activity backup and restore. Credentials and tokens are excluded, and restored mappings are prepared for a fresh FredKI synchronization.


## 1.4.0-rc2 regression recovery

This release restores the proven synchronous import and credential-list workflow from 1.3.0 after the experimental RC1 AMD and ad-hoc-task conversion caused runtime regressions. The secure token model, Privacy API, database migration, HTTP wrapper, and backup/restore improvements remain in place. A future background-processing conversion must be introduced only after full Moodle runtime testing and correctly compiled AMD assets are available.


## Security and privacy notes

- Since 1.6.7-rc2, FredKI passwords are encrypted at rest for automatic re-login, outside the activity table. Existing passwords cannot be migrated because they were never stored.
- The plugin stores a user-scoped FredKI access token with a short local expiry; expiring tokens are renewed instead of deleting authorisation. Passwords and tokens are excluded from course backups and privacy exports.
- Configuration AJAX endpoints require POST, a valid Moodle session key, the relevant course context, and `mod/fredkicourseimport:addinstance`.
- User-facing errors are neutral; complete external API responses are not stored or displayed.
- Course backups exclude passwords, tokens, user authorisations, and external FredKI document identifiers.
- Removing a file schedules or performs its FredKI document deletion through the normal plugin workflow. If an entire activity is deleted before that workflow completes, external cleanup may need to be completed administratively in FredKI.

## Supported versions

This test candidate targets Moodle **5.2**. Moodle **5.1** is a secondary test goal;
Moodle 5.3 is not verified. The `requires` declaration is inherited from the
original plugin and does not constitute evidence of testing older Moodle versions.
Installation, upgrade, access checks, backup/restore and FredKI API interactions
must be verified on a real Moodle 5.2 test instance before production use.

## Search-optimierter Import (ab 1.6.0)

FredKI-Einträge werden so aufgebaut, dass MaxKB Volltext-, Vektor- und Hybridsuche
stärker von den unterscheidenden Moodle-Metadaten profitieren:

- Abschnittstitel = tatsächlicher Aktivitäts-/Materialname
- Custom Fields/Tags (`Materialtyp`, `Zielgruppe`, `Thema` usw.) direkt am Anfang
- kompaktere Metadaten ohne doppelte Moodle-Quellangaben
- direkter Aktivitätslink und Kurslink bleiben erhalten

Generische Fragen wie `Welche Buchtipps gibt es?` werden bewusst **nicht** mehr an
jedes Material gehängt. MaxKB indexiert verknüpfte Fragen separat; identische Fragen
an vielen Dokumenten können sonst unterschiedliche Materialien künstlich ähnlich
machen. Materialtyp, Zielgruppe, Thema und Abschnittshierarchie bleiben stattdessen
im normalen Abschnittstext und als echte FredKI-Tags durchsuchbar.


## Abschnittshierarchie (ab 1.6.1)

Bei Moodle-Unterabschnitten (`mod_subsection`, ab Moodle 4.5) übernimmt FredKI nicht
mehr nur den unmittelbar zugeordneten Abschnitt. Der Import rekonstruiert auch die
übergeordneten Moodle-Abschnitte. Ein Material kann dadurch beispielsweise so
erscheinen:

```text
Wo ist mein Hut?
Materialtyp: Buch
Übergeordneter Abschnitt: Büchertipps – Spannend bis lustig – Lesestoff, der motiviert
Unterabschnitt: Bücher für jüngere Grundschulkinder
Beschreibung: ...
```

Bei normalen, nicht verschachtelten Kursabschnitten wird weiterhin nur
`Abschnitt: ...` ausgegeben. Die Hierarchie fließt auch in die Metadaten-Fingerprints
ein, damit Änderungen an Abschnittsüberschriften über „nur Änderungen aktualisieren“
erkannt werden.


## Suchverhalten

CourseImport erzeugt **keine verknüpften Fragen (`problem_list`)**.

## 1.6.7-rc2: Verbindung und Backup-Korrektur (Moodle 5.2)

- Aktivitäts-Backup lädt `backup_fredkicourseimport_stepslib.php` explizit; gleicher Fix für Restore.
- Kurze FredKI-Zugriffstokens werden automatisch durch eine Neuanmeldung erneuert.
- Das FredKI-Kennwort wird für diese Erneuerung verschlüsselt mit `core\encryption` und dem schreibgeschützten Moodle-Server-Schlüssel in der Auth-Tabelle gespeichert, nie in der Aktivität oder im Kursbackup.
- Bei bereits installierten Versionen sind die alten Passwörter **nicht wiederherstellbar**. Nach dem Upgrade einmalig über die Einstellungen der bestehenden Aktivität Benutzername und Kennwort neu eingeben. Wissensbasis-ID, Kurszuordnung und FredKI-Dokumente nicht neu anlegen.
- Mit gültiger gespeicherter Authentifizierung bleiben alle Zuordnungen bei Codeupdates erhalten (normales Moodle Plugin-Upgrade, **keine Deinstallation**).
- Bei einem vollständigen Kurs-Restore bleiben FredKI-Zugangsdaten bewusst aus Sicherheitsgründen ausgeschlossen und die Ziel-Wissensbasis wird nicht automatisch übernommen.
- Moodle-Dataroot und Verschlüsselungsschlüssel dürfen bei Serverumzügen nicht verlorengehen; sonst muss erneut autorisiert werden.

Diese Version ist ein RC-/Testkandidat und benötigt einen Integrationstest in einer echten Moodle-5.2-Umgebung.
