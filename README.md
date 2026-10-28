# FredKI course data (mod_fredkicourseimport)

Moodle activity module that synchronises selected Moodle course content with a
FredKI knowledge base, so that a FredKI chatbot can answer questions based on the
course material.

## Requirements

- Moodle 4.5 or later (tested with Moodle 5.2 on PostgreSQL and MariaDB).
- A FredKI account (username and password) and at least one FredKI knowledge base.
  FredKI is an external service with its own terms of use and is not part of this
  plugin. To obtain an account, please contact lernsteine.com.
- The HTTPS base URL of your FredKI installation (configured by the site administrator),
  for example `https://fredki.lernsteine.com`.

## Features

- FredKI login inside the activity settings, with an optional "remember login"
  checkbox. Remembered passwords are encrypted with Moodle `core\encryption`.
- Selection of the target FredKI knowledge base.
- Selection of the course content to synchronise: files (PDF, Office documents),
  section texts, activity descriptions, pages, text areas, books, lessons, glossaries,
  URLs, H5P and SCORM activities.
- Glossaries in complete mode: every approved entry becomes its own FredKI document,
  named after the concept and linking directly to the entry. The glossary description
  is transferred once as a separate overview document.
- "Update changes" action that only transfers new, changed or deleted content.
- Moodle section hierarchy (including subsections) and custom fields are added as
  searchable metadata and FredKI tags.

## Installation

1. Copy the plugin directory to `mod/fredkicourseimport` (or install the ZIP via
   *Site administration > Plugins > Install plugins*).
2. Run the Moodle upgrade from *Site administration > Notifications*.
3. Go to *Site administration > Plugins > Activity modules > FredKI course data* and
   enter the FredKI base URL. The default login path `/admin/api/user/login` normally
   does not need to be changed.

## Usage

1. Add the activity "FredKI course data" to a course.
2. In the activity settings, log in with your FredKI account and choose the knowledge base.
3. Open the activity, select the course content to transfer and start the import.
4. Later, use "Update changes" to keep the knowledge base in sync with the course.

## Privacy and security

- The plugin declares all stored and externally transmitted data through the Moodle
  Privacy API, including the transfer of course content and login data to FredKI.
- FredKI passwords are never stored in the activity record or in course backups.
  If "remember login" is used, the password is stored encrypted in the
  `fredkicourseimport_auth` table together with a short-lived, user-bound access token.
- All configuration endpoints require a logged-in user, a valid session key and the
  relevant capabilities. Import actions require `mod/fredkicourseimport:sync`.
- Course backups exclude passwords, tokens, user authorisations and external FredKI
  document identifiers. After a restore, the FredKI connection must be set up again.
- Debug logging should not be enabled on production sites.
- The Moodle data directory and encryption key must be preserved when moving a site;
  otherwise users need to log in to FredKI again.

## Bug reports and support

Please report issues at
https://github.com/lernsteine/moodle-mod_fredkicourseimport/issues

## License

2026 lernsteine.com

This program is free software: you can redistribute it and/or modify it under the
terms of the GNU General Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this
program. If not, see https://www.gnu.org/licenses/.
