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

The Moodle plugin source code is licensed under GNU GPL v3 or later. You may modify and redistribute the plugin under those terms. The external FredKI service may require separate commercial credentials or a service licence; see `FREDKI-SERVICE-ACCESS.md`.

## Notes

The chatbot is displayed through the public FredKI embed script. Agent configuration itself remains in FredKI.


## Security and privacy

AJAX configuration endpoints require valid Moodle login, session keys and appropriate
capabilities. The import actions require `mod/fredkicourseimport:sync` and POST with a
valid session key. FredKI login passwords are not stored in activity records. If the administrator explicitly enables automatic reconnection, encrypted credentials can be stored in the user-specific authorisation table. The plugin stores a user-bound access token in the
`fredkicourseimport_auth` table. The plugin declares its stored and externally
transmitted data through the Moodle Privacy API. Debug logging should not remain
enabled on production sites.

## Backup and restore

The plugin implements Moodle activity backup and restore. Credentials and tokens are excluded, and restored mappings are prepared for a fresh FredKI synchronization.


## 1.4.0-rc2 regression recovery

This release restores the proven synchronous import and credential-list workflow from 1.3.0 after the experimental RC1 AMD and ad-hoc-task conversion caused runtime regressions. The secure token model, Privacy API, database migration, HTTP wrapper, and backup/restore improvements remain in place. A future background-processing conversion must be introduced only after full Moodle runtime testing and correctly compiled AMD assets are available.


## Security and privacy notes

- FredKI passwords are used only for the immediate login request and are never stored in the activity table.
- The plugin stores a user-scoped FredKI access token with a configurable local expiry. Tokens are excluded from course backups and privacy exports.
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


## Verified-deletion synchronization (1.6.8-rc11)

The 1.6.8-rc10 candidate is intentionally based on the tested `1.6.8-rc1-glossar-OK` code.
It keeps per-entry glossary links, manual selection and FredKI token renewal.

- Moodle Cron checks every 15 minutes for **physically removed** course activities, individual glossary entries and identifiable original resource/folder files.
- Each identified original is independently checked in the Moodle database or the original file area before removing the exact document mapping from FredKI.
- Hidden course items, changed availability, changed group membership and incomplete/generated exports are **not** evidence of deletion. Authorized course editors may explicitly select hidden materials for publication in the shared FredKI bot; this does not change Moodle access controls.
- Maximum three verified removals per CourseImport activity in any scheduled task run. If more than three are detected together, **the entire automatic deletion batch is blocked** until reviewed. No gradual mass deletion.
- Unattended and automatically inferred missing-original deletion batches remain limited to three remote documents per CourseImport activity.
- A deliberate manual deselection uses a separate confirmation page listing the exact deselected Moodle sources and the current count of affected FredKI documents. This supports activities such as glossaries with multiple individual documents. Only the confirmed source IDs are eligible for manually initiated deletion.
- Explicitly deselecting previously imported content requires a second POST with a signed confirmation token. The token is bound to the current user, session, previous and requested selections, content modes, and the current mapped FredKI document identifiers. The preview does not modify stored selections or call the FredKI API.
- If the selection or document mappings change between preview and confirmation, the confirmation must be repeated. An unrelated already-unselected source is never implicitly approved for deletion. The normal refresh button never approves manual deselections.
- A failed FredKI API deletion retains its document ID and `deleted` status for retry. This plugin never deletes original Moodle course content.
- Synchronisation after physically removing a single Moodle activity or glossary entry may complete before the next scheduled run. Running Moodle Cron is required for unattended removal.
- For deletion of a file *inside a still-present folder activity*, the module's edit timestamp must be newer than its last synchronization and the original source filename must be absent.
- Deletion of complete Moodle courses or of the CourseImport activity itself is not silently applied to entire FredKI knowledge bases. The administrative cleanup register still needs review.

**Important:** Do not enable this scheduled cleanup in production until a representative Moodle 5.2 + FredKI staging test has confirmed its behaviour. First take backups. A Moodle test environment has not been available for the preparation of this candidate.

## 1.6.8-rc11: confirming manually deselected content

1. Open **Choose content for import**, uncheck one or more previously published Moodle items, then click **Synchronise selected content**.
2. Moodle displays a separate confirmation page with the *names of the removed sources* and the *number of existing FredKI documents* related to them. No changes are made at this step.
3. Click **Confirm removal and synchronise**, or cancel to keep the existing stored selection unchanged.
4. After synchronisation, check the mapping status for exactly those source documents.

This fixes the earlier RC10 error raised when a teacher unchecked a source but did not
find the manual confirmation checkbox at the bottom of a long list. The
maximum-three safeguard remains unchanged for automatic missing-original deletions.
Manual revocations are separate, require explicit per-request consent, and are
restricted to exactly the previously selected Moodle sources on the approval page.

Important: the plugin has **not** been tested against a live Moodle 5.2 / FredKI
server in this build environment. Test on staging before production use.


## 1.6.8-rc13: streamlined connection settings

The course view no longer displays a connection-status banner or a credential-management
button when FredKI authorisation works. Automatic token renewal remains enabled for
connections whose authorising user explicitly opted in to encrypted credential storage.
To renew or change credentials, open **CourseImport activity → Settings → FredKI access**
and expand **Manage access credentials** to enter a username and password directly
in the activity settings. Click **Reconnect** without leaving the settings page.
This is per-activity authorisation, not a site-wide shared
username/password. If authorisation fails, the course view shows a warning pointing to
Settings, not a password form. The knowledge base and existing documents are preserved.

This is a release candidate for **Moodle 5.2** and still requires Moodle Plugin CI
(including PostgreSQL and MariaDB), an installation/upgrade test, permissions and privacy
validation, and a full FredKI sandbox regression test before stable designation.


## 1.6.8-rc14: inline credential renewal

In the activity's Settings → FredKI access area, **Manage access credentials**
expands inline (no link to a separate course page). Editors can provide a username,
password and optional renewed consent for encrypted storage, then reconnect via a
separate authenticated POST. This does **not** submit other Moodle activity settings,
change the selected knowledge base or run synchronization. The former authoriser's
Moodle-side token is revoked only after a replacement account has successfully
authenticated and been confirmed to access the existing knowledge base.

The automatic token renewal, glossary direct links, import selection, and safe-delete
code remain unchanged from rc13. The live Moodle/FredKI connection flow must still
be verified on a staging instance before declaring STABLE.


## 1.6.8-rc15 — Marketplace preflight (2026-10-21)

This candidate is based on the **user-tested rc14**. The glossary, import selection,
manual and scheduled deletion, authentication/token manager and chat integration
remain byte-identical to rc14, except for the version metadata and Privacy API
external-entitlement disclosure. No new deletion behaviour is introduced.

The plugin is **not yet approved by Moodle Marketplace**. Run Moodle Plugin CI,
verify installation/upgrade and scheduled deletion on Moodle 5.2 with an isolated FredKI
test workspace, and supply reviewer access and rights-cleared screenshots.
An active FredKI service entitlement and account may be necessary. The plugin
checks the entitlement externally; see FREDKI-SERVICE-ACCESS.md.
