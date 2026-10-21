# FredKI service access and GPL code license

The **Moodle plugin source code** in this archive is available under GNU GPL v3 or later (see `COPYING`). You may run, modify and redistribute the plugin under the GPL. The previously used proprietary no-redistribution notice does **not** apply to this plugin code.

**External service access is separate.** Use of a hosted FredKI service, workspace, API, AI models, hosting or related commercial services may require valid credentials, a paid subscription and/or a service contract with the relevant provider. The GPL license on this plugin does not grant access to those external services.

The existing Moodle configuration includes a separate FredKI service entitlement check by contacting `https://bildungbleibt.de/generator/check.php` with a service key, site hostname and plugin identifier. That requirement is an **operational service access check**, not a legal condition on copying, using, examining, modifying or redistributing the GPL plugin source code. The runtime service check is unchanged by this licensing-only release candidate.

To use the external FredKI knowledge bases, admins also configure FredKI account credentials. The plugin optionally stores encrypted credentials for reauthorisation only if the editor explicitly opts in. An optional chatbot embed contacts the separate FredKI service. Administrators should confirm their own privacy, data processing, data transfer and subscription arrangements before enabling these features.

The FredKI brand name and external service are not themselves licensed by the plugin's GPL grant; any independent trade mark rights remain with their respective rights holders.


### Entitlement server / transparency
The service entitlement check sends `licensekey`, the Moodle site hostname
(`domain`), and the fixed identifier `mod_fredkicourseimport` to
`https://bildungbleibt.de/generator/check.php`. Validation results are cached
for up to 24 hours. This separate third-party transfer is declared in the
plugin Privacy API as `fredki_entitlement`. The site administrator should
include it in the site's privacy information.
