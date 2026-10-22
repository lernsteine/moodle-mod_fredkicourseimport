# FredKI Course Import — Moodle Marketplace release gate

Status: **Release candidate, not yet approved for stable release**.

## Candidate and safe rollback

- Tested candidate branch: `fix/moodle-style-cleanup-20261022`
- Frozen safety copy: `backup/fredki-rc15-user-tested-20261022`
- Plugin component: `mod_fredkicourseimport` (must not change)
- Existing release: `1.6.8-rc15-marketplace-preflight`
- Proposed stable release **after all checks pass**: `1.7.0`
- First declared compatibility target: Moodle 5.2 only

## Required automated verification (must all pass)

Run the manual workflow `.github/workflows/moodle-ci.yml` against the exact final commit of the repair branch once all source checks are complete:

- [ ] Moodle 5.2 / PHP 8.3 / PostgreSQL: installation, phplint, validate, codechecker, savepoints, phpdoc, phpunit
- [ ] Moodle 5.2 / PHP 8.3 / MariaDB: installation, phplint, validate, codechecker, savepoints, phpdoc, phpunit
- [ ] Review diffs against the original RC15 baseline for accidental behavioural changes
- [ ] Confirm no warnings requiring changes for Marketplace review

## Functional staging verification

The user reported that almost all functions worked in their Moodle test environment. Record exact coverage before declaring a stable version:

- [ ] New installation and upgrade from a pre-existing install
- [ ] Connect and reconnect to FredKI; select an existing knowledge base
- [ ] Import a sample Moodle course and verify source links and content
- [ ] Re-sync changed content and verify no unwanted duplicate documents
- [ ] Agent selection and optional course chatbot display
- [ ] Manually deselect content and confirm exactly the intended deletions
- [ ] Scheduled missing-source cleanup; test the three-document safeguard
- [ ] Backup/restore, capabilities, privacy export/deletion behaviour
- [ ] Ensure all destructive tests use a separate disposable FredKI knowledge base

## Marketplace submission package

- [ ] English plugin summary and screenshots with publication rights
- [ ] Documentation for external FredKI service, credentials, licensing and privacy
- [ ] GPL-compatible source package rooted at `fredkicourseimport/`
- [ ] Update `version.php` to stable version and maturity **only after acceptance checks**
- [ ] Re-run automated checks on the exact stable-release commit
- [ ] Tag stable release and produce ZIP only after tests pass
- [ ] Submit to Moodle Marketplace for review; do not claim approval beforehand

Do not merge the draft PR into `main` or extend supported Moodle versions without corresponding proof.
