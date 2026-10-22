# FredKI Course Import — Compatibility and human review gate

Review date: 2026-10-22

## Review objective
Prepare one carefully reviewed release for Moodle **4.5, 5.0, 5.1, 5.2 and 5.3**. Do not claim compatibility solely by changing version metadata or by making CI green. The current candidate remains an RC.

## Confirmed baseline facts
- Current `version.php`: `requires = 2026042000`; `supported = [502, 502]`; `release = 1.6.8-rc15-marketplace-preflight`; `MATURITY_RC`. Therefore Moodle 4.5 is **not** currently declared compatible.
- Current Moodle CI covers Moodle 5.2 / PHP 8.3 with PostgreSQL and MariaDB. No complete proof exists here for Moodle 4.5–5.3.
- The plugin has a Moodle Privacy API provider, activity backup/restore implementations, external services, token/credential management, manual-selection safeguards and scheduled external deletion logic. Presence of an implementation does not prove correctness.
- The code uses `\\core\\encryption` for optional stored credentials. This API exists in Moodle 4.5; other Moodle API calls still require version-by-version review.
- A user reported a successful practical trial of nearly all features on a test instance, but exact versions and tested scenarios are not documented.

## Compatibility verification matrix

| Moodle | PHP | Database variants | Evidence required |
|---|---|---|---|
| 4.5 LTS | 8.1 (also 8.3) | PostgreSQL, MariaDB | New install, upgrade, CI, functional checks |
| 5.0 | 8.2 | PostgreSQL, MariaDB | New install, upgrade, CI, functional checks |
| 5.1 | 8.2 | PostgreSQL, MariaDB | New install, upgrade, CI, functional checks |
| 5.2 | 8.3 | PostgreSQL, MariaDB | Repeat CI after changes, functional checks |
| 5.3 LTS | 8.3 | PostgreSQL, MariaDB | New install, upgrade, CI, functional checks |

## Required code review (not only mechanical formatting)

- [ ] **API compatibility**: review all Moodle core classes/functions and changes to directory layout, course format, subsections, hooks, events and forms across supported versions.
- [ ] **Security**: authentication, capability checks, sesskey validation, request types, input validation, token lifecycle, external URL allowlisting, SSRF, embedded scripts, logging and exception handling.
- [ ] **Privacy**: external data disclosure, token/credential storage, erasure/export requests, backups containing no secrets or external identifiers.
- [ ] **Deletion safety**: manual confirmation scope, signed approval, idempotent remote deletion, missing-source detection, three-deletion safeguard, scheduled task concurrency and audit logging.
- [ ] **Upgrades**: preserve already-deployed savepoints and schema; verify upgrades from realistic previous releases on each targeted Moodle version.
- [ ] **Quality**: Moodle Codechecker, PHPDoc, phplint, plugin validate, savepoints, PHPUnit; resolve issues correctly rather than suppressing failures.
- [ ] **Packaging**: plugin directory `fredkicourseimport`, GPL headers and attributions, English code comments, documented dependency on external FredKI service, public issue tracker, screenshots and manual.
- [ ] **Human review**: verify PHPDoc describes actual types/behaviour (no filler text such as “parameter value” or incorrect `mixed`), meaningful naming, structured error handling, focused tests and understandable architecture.

## Version metadata decision
Only after **all intended** Moodle versions are verified, consider:
```php
$plugin->requires = 2024100700;
$plugin->supported = [405, 503];
```
Do **not** apply these declarations before verification, and do not set `MATURITY_STABLE` until the release candidate passes all gates.

## Editorial acceptance
Reviewers should see specific documentation of why Moodle compatibility holds, clearly described integration boundaries, deterministic tests, and careful treatment of destructive operations—not generic automated comments or unsupported marketing claims.

Sources: Moodle Developer Resources — PHP policy; Moodle 4.5 and 5.3 release notes; Plugin contribution checklist.
