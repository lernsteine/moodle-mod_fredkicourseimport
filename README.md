# FredKI CourseImport for Moodle

FredKI CourseImport is a Moodle activity module (`mod_fredkicourseimport`) for exporting selected course resources and glossary entries to a FredKI/MaxKB knowledge base.

**Status:** repository preparation in progress. The source files have not yet been pushed to this repository. Do not use this repository as an installable release.

The current tested packaging baseline is **1.6.8-rc15** (release candidate). The intended first public release will be **1.6.8** after Moodle Plugin CI, integration tests, and review. The `rc15` suffix is a development identifier, not part of the final product name.

## Features

- Manual Moodle course content selection (full content or reference)
- Individual glossary entries with direct Moodle links
- Optional encrypted credentials and token renewal
- Explicit confirmation of manually deselected content
- Guarded deletion synchronization for removed original resources

## Source and release checks

The source archive, GPL-3.0-or-later licensing information, test suite, and Moodle Plugin CI workflow are prepared separately. They must be pushed before the repository is ready for CI or marketplace submission.

**Security:** never commit Moodle administrator credentials, FredKI API secrets, access tokens, or production data.

Copyright © 2026 lernsteine.com. Licensed under GNU GPL v3 or later.
