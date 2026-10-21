<?php
// This file is part of FredKI CourseImport for Moodle.
// Copyright (c) 2026 lernsteine.com
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See <https://www.gnu.org/licenses/>.

/**
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */












$string['pluginname'] = 'FredKI course data';
$string['modulename'] = 'FredKI course data';
$string['modulenameplural'] = 'FredKI course data';
$string['pluginadministration'] = 'Manage FredKI course data';
$string['modulename_help'] = 'Imports existing course content directly to FredKI.';
$string['knowledgeid'] = 'Knowledge base ID';
$string['knowledgebasename'] = 'Knowledge base name';
$string['files'] = 'Generated import files';
$string['nofiles'] = 'No course content has been imported yet.';
$string['syncstatus'] = 'Synchronization status';
$string['nosyncentries'] = 'No entries in the sync table yet.';
$string['fredkidocumentid'] = 'FredKI document ID';
$string['fredkicourseimport:view'] = 'View FredKI course import';
$string['lastsync'] = 'Last synchronization';
$string['baseurl'] = 'FredKI base URL';
$string['loginpath'] = 'FredKI login path';
$string['debuglog'] = 'Enable debug logging';
$string['status_pending'] = 'pending';
$string['status_synced'] = 'synchronized';
$string['knowledgebase'] = 'Knowledge base';
$string['status_deleted'] = 'deleted';
$string['status_error'] = 'error';
$string['fredkisettingsheader'] = 'FredKI access and knowledge base';
$string['licensekey'] = 'License key';
$string['licensekey_desc'] = 'Enter the license key for this Moodle site.';
$string['invalidlicense'] = 'This plugin is not licensed for this Moodle site.';
$string['fredkiusername'] = 'FredKI username';
$string['fredkipassword'] = 'FredKI password';
$string['availableknowledgebases'] = 'Available:';
$string['chooseknowledgebase'] = 'Please select a knowledge base';
$string['loadknowledgebases'] = 'Load knowledge base';
$string['knowledgeidrequired'] = 'Please select a knowledge base.';
$string['configureactivityfirst'] = 'Please first save FredKI username, password and knowledge base in this activity settings page.';
$string['activitymappinglockedinfo'] = 'The mapping has already been set up and can no longer be changed. Create the activity again if needed.';
$string['authorizedby'] = 'Authorized by';
$string['importallcoursecontent'] = 'Send all course data to FredKI';
$string['importdone'] = 'Import completed. Created: {$a->created}, updated: {$a->updated}, unchanged: {$a->skipped}, deleted: {$a->deleted}. Processed: {$a->files} files, {$a->sectiontexts} section texts, {$a->activitytexts} activity descriptions, {$a->urls} links, {$a->glossaries} glossary entries, {$a->pages} pages, {$a->labels} text areas, {$a->books} book chapters, {$a->lessons} lessons, {$a->h5p} H5P guides, {$a->scorm} SCORM guides.';
$string['entercredentialstoload'] = 'Please enter username and password first.';
$string['knowledgebasesloading'] = 'Loading knowledge bases ...';
$string['knowledgebasesloaderror'] = 'Error loading knowledge bases.';
$string['knowledgebasesloaded'] = 'knowledge bases loaded.';
$string['missingbaseurl'] = 'The FredKI base URL is not configured.';
$string['filenotfound'] = 'File not found in Moodle.';
$string['licenseblockmessage'] = 'FredKI functions are blocked because no valid license is available.';
$string['connectionoverview'] = 'Connection and target';
$string['generateddocumentscount'] = 'Generated documents';
$string['synceddocumentscount'] = 'Synced';
$string['pendingdocumentscount'] = 'Pending';
$string['errordocumentscount'] = 'Errors';
$string['generatedfilepath'] = 'Area';
$string['generatedfilename'] = 'File';
$string['generatedfilesize'] = 'Size';
$string['glossaryname'] = 'Glossary';
$string['glossarydefinition'] = 'Definition';
$string['glossaryentries'] = 'Glossary entries';
$string['untitledglossaryentry'] = 'Untitled glossary entry';
$string['sourcelabel'] = 'Text and media area';
$string['processedcontentcount'] = 'Processed content';
$string['createddocumentscount'] = 'Created';
$string['updateddocumentscount'] = 'Updated';
$string['skippeddocumentscount'] = 'Unchanged';
$string['deleteddocumentscount'] = 'Deleted';
$string['status_removed'] = 'deleted';
$string['syncprogress'] = 'Synchronization progress';
$string['syncrunning'] = 'Import is running. Please do not close this page.';
$string['syncrunningstep'] = 'Collecting course data and sending it to FredKI ...';
$string['progressstarting'] = 'Preparing import ...';
$string['invalidlicensemissing'] = 'No license key has been configured for FredKI Course Data yet.';
$string['invalidlicensedomain'] = 'The Moodle domain could not be detected.';
$string['invalidlicenseserverempty'] = 'The license server did not respond.';
$string['invalidlicenseserverresponse'] = 'The license server returned an invalid response.';
$string['license_required_for_activity'] = 'Please enter a valid license key in the plugin settings first. After that, knowledge bases can be loaded and activities can be used.';
$string['choosecontentforimport'] = 'Select course content and send to FredKI';
$string['selectcontentintro'] = 'Select which course content should be sent to FredKI. If you select nothing, all existing content from this activity will be removed from the FredKI knowledge base during the next synchronization.';
$string['startselectedimport'] = 'Send selected content to FredKI';
$string['coursesectiontexts'] = 'Course section texts';
$string['nothingselected'] = 'No content selected. Existing content will be removed from the FredKI knowledge base.';
$string['ready'] = 'Ready';
$string['processingupload'] = 'Processing…';
$string['selectvisible'] = 'Visible for participants';
$string['selection'] = 'Selection';
$string['hiddenforparticipants'] = 'Currently hidden for participants';
$string['selectall'] = 'All';
$string['deselectall'] = 'None';
$string['fredkiagentheader'] = 'FredKI agent';
$string['agentid'] = 'FredKI agent';
$string['agentid_help'] = 'Select the FredKI agent here. Agents are loaded with the stored FredKI credentials.';
$string['fredkiembedcode'] = 'FredKI embed code';
$string['fredkiembedcode_help'] = 'Paste the FredKI embed code here. It should start with <script async defer src="https://fredki.lernsteine.com/chat/api/embed?...">. The plugin only outputs FredKI embed scripts from the configured FredKI domain.';
$string['chooseagent'] = 'Please select an agent';
$string['availableagents'] = 'Available agents';
$string['loadagents'] = 'Load agents';
$string['agentsloading'] = 'Loading agents ...';
$string['agentsloaderror'] = 'Error loading agents.';
$string['agentsloaded'] = 'agents loaded.';
$string['fredkiembedauto'] = 'The embed code is generated automatically from the selected agent. The field is internal and does not need to be edited manually.';
$string['agentcoursevisible'] = 'Show chatbot on course page';
$string['agentcoursevisible_help'] = 'When enabled, the FredKI embed script is output on the course page so the selected agent appears directly in the course.';
$string['fredkichaturl'] = 'FredKI chat URL';
$string['fredkichaturl_help'] = 'Paste the public chat URL of the agent, e.g. https://fredkitest.lernsteine.com/chat/ff19702075bfdb0b. The plugin automatically generates the embed code from it.';
$string['fredkichaturl_desc'] = 'Public chat URL of the selected FredKI agent. If FredKI does not provide the URL when loading agents, it can be pasted here manually.';
$string['selectedknowledgebase'] = 'Selected knowledge base:';
$string['noknowledgebasechosen'] = 'No knowledge base selected yet.';
$string['selectknowledgebasebutton'] = 'Select';
$string['selectedknowledgebasebutton'] = 'Selected';
$string['defaultactivityname'] = 'Send course content to FredKI';
$string['includeactivitycontent'] = 'Transfer activity content';
$string['fredkicourseimport:manage'] = 'Manage FredKI course import';
$string['invalidcoursecontext'] = 'The course context could not be determined.';
$string['invalidjsonrequest'] = 'The request could not be processed.';
$string['privacy:metadata:fredkicourseimport'] = 'Configuration data for the FredKI course import activity.';
$string['privacy:metadata:fredki'] = 'FredKI is an external system to which selected Moodle course data is transferred.';
$string['privacy:metadata:fredki:username'] = 'The FredKI username used for authentication.';
$string['privacy:metadata:fredki:password'] = 'The FredKI password used for authentication.';
$string['privacy:metadata:fredki:coursecontent'] = 'Course information selected by an authorised user.';
$string['fredkicourseimport:addinstance'] = 'Add a FredKI course import activity';
$string['externalapierror'] = 'The FredKI service could not complete the request.';
$string['externalapiinvalidresponse'] = 'The FredKI service returned an invalid response.';
$string['missingvalidtoken'] = 'FredKI authorisation has expired or was rejected. Open this CourseImport activity’s Settings and re-authorise the connection there. Your knowledge base is preserved.';
$string['invalidrequestmethod'] = 'This request method is not allowed.';
$string['privacy:metadata:auth'] = 'User-specific FredKI authorisation data.';
$string['privacy:metadata:auth:userid'] = 'The Moodle user ID.';
$string['privacy:metadata:auth:username'] = 'The FredKI username.';
$string['privacy:metadata:auth:token'] = 'The secret FredKI access token.';
$string['privacy:metadata:auth:tokenexpires'] = 'The local token expiry time.';
$string['privacy:metadata:auth:lastused'] = 'The last token use time.';
$string['privacy:metadata:fredki:browserdata'] = 'Browser connection data sent when the optional FredKI chat embed is loaded.';

$string['fredkicourseimport:sync'] = 'Synchronize FredKI course import';

$string['invalidbaseurl'] = 'The FredKI base URL must be a valid HTTPS address.';
$string['licenseconfigurefirst'] = 'Configure and validate the FredKI licence in the plugin settings before using the FredKI functions.';


$string['contentmodeknowledge'] = 'Complete';
$string['contentmodereference'] = 'Reference';
$string['contentmode_help'] = 'Complete transfers the contents provided by Moodle. For a glossary, each approved entry is indexed separately with its own title, definition and direct Moodle entry link. Reference transfers only the glossary title, description and overview link.';
$string['importrunning'] = 'Import running ...';
$string['documenttype_material'] = 'Material';
$string['documenttype_section'] = 'Course section';
$string['documenttype_resource'] = 'File';
$string['documenttype_folder'] = 'Folder';
$string['documenttype_url'] = 'Link';
$string['documenttype_glossary'] = 'Glossary';
$string['documenttype_page'] = 'Page';
$string['documenttype_label'] = 'Text and media area';
$string['documenttype_book'] = 'Book';
$string['documenttype_lesson'] = 'Lesson';
$string['documenttype_h5p'] = 'H5P';
$string['documenttype_scorm'] = 'SCORM';

$string['applytoselected'] = 'For selected files';
$string['apply'] = 'Apply';

$string['documentextractfailed'] = 'The document content could not be extracted by FredKI. The file may be encrypted, damaged, image-only, too large, or use an unsupported PDF/Office variant.';
$string['updatechanges'] = 'Update changes';
$string['updatechangesonly'] = 'Update changes only';
$string['refreshdone'] = 'Changes updated. Created: {$a->created}, updated: {$a->updated}, unchanged: {$a->skipped}, deleted: {$a->deleted}.';

// Moodle 5.2 suite audit, version 1.6.6-rc1.
$string['syncalreadyrunning'] = 'A FredKI import is already running for this activity. Please try again when it has finished.';
$string['cleanupregister'] = 'External FredKI documents – cleanup register';
$string['cleanupregisteropen'] = 'Review document identifiers saved after deleting a Courseimport activity';
$string['cleanupregisterwarning'] = 'This is a read-only list, not evidence that remote documents still exist. Check FredKI before removing anything. These entries are NOT deleted automatically. No passwords or access tokens are stored here. Display is limited to 1,000 newest entries.';
$string['cleanupcourseid'] = 'Original Moodle course ID';
$string['cleanupworkspace'] = 'FredKI workspace';
$string['cleanupknowledgeid'] = 'Knowledge base ID';
$string['cleanupdocumentid'] = 'Remote document ID';
$string['cleanupregistered'] = 'Recorded on';
$string['cleanupempty'] = 'No remote document IDs have been registered for deleted activities.';
$string['privacy:metadata:activity'] = 'Authorising user for the import';
$string['privacy:metadata:activity:authorizedbyuserid'] = 'Moodle user ID originally authorised to operate this FredKI import.';
$string['reauthheading'] = 'Reconnect FredKI';
$string['reauthhelp'] = 'Sign in again. The knowledge base and all imported documents remain unchanged.';
$string['reauthremember'] = 'With consent, encrypt and retain credentials for automatic token renewal (back up the Moodle server encryption key).';
$string['reauthbutton'] = 'Reconnect';
$string['reauthsuccess'] = 'FredKI connection renewed successfully.';
$string['reauthmissingcredentials'] = 'Username and password are required.';
$string['reauthknowledgeinaccessible'] = 'This FredKI account cannot access the linked knowledge base. No changes have been made.';
$string['authrenewbusy'] = 'Another request is renewing FredKI authorisation. Please try again.';
$string['reauthlink'] = 'Re-authorise connection at any time';
$string['reauthwarning'] = 'FredKI authorisation has expired or was rejected. Open this CourseImport activity’s Settings to re-authorise FredKI access. Your existing knowledge base is preserved.';

$string['invalidglossaryentry'] = 'This glossary entry is no longer approved or cannot be verified. Please synchronize the sources again.';

$string['confirmunselect'] = 'I confirm that deselected content will be removed from the FredKI knowledge base on synchronisation.';
$string['confirmunselectrequired'] = 'Previously published content has been deselected. Confirm its removal below the selection before continuing.';
$string['deletionbatchblocked'] = 'Safety stop: {$a} deletions require review. The batch was not deleted. Contact the site administrator.';
$string['taskreconciledeleted'] = 'FredKI: reconcile sources physically deleted in Moodle';

$string['privacy:metadata:auth:passwordencrypted'] = 'Optional encrypted password stored only when automatic re-authorisation is explicitly enabled.';

// Explicit, scoped confirmation for manual deselection (rc11).
$string['deselectionreviewheading'] = 'Confirm removal of selected content';
$string['deselectionreviewintro'] = 'You have deselected course content that was previously published. No upload or deletion has happened yet. Review the following sources; only these sources are authorised for removal from FredKI.';
$string['deselectionremotecount'] = 'FredKI documents currently mapped to these sources: {$a}. A glossary can contain many documents per selected Moodle activity.';
$string['deselectionconfirmbutton'] = 'Confirm removal and synchronise';
$string['deselectionreviewhint'] = 'Deselecting previously published content opens a separate confirmation page showing the affected sources and document count before synchronising.';
$string['deselectionapprovalexpired'] = 'The selection or remote document mappings changed after confirmation. Please review and confirm the updated list again.';
$string['missingmoodlesource'] = 'Deleted course activity (not manually removed by this selection)';
$string['autorenewactive'] = 'Automatic FredKI re-authentication is enabled. The plugin renews access tokens in the background when needed. Regular manual sign-in is not required.';
$string['autorenewinactive'] = 'Automatic re-authentication is not enabled yet. Sign in once and explicitly permit encrypted credential storage; afterwards tokens will be renewed automatically.';
$string['reauthenable'] = 'Set up automatic re-authentication once';
$string['reauthmanage'] = 'Change FredKI credentials or manage automatic re-authentication';
$string['autorenewretained'] = 'Automatic re-authentication was previously enabled for this connection. It stays enabled while this checkbox remains selected.';

$string['reauthsettingslabel'] = 'FredKI access';
$string['reauthsettingslink'] = 'Re-authorise the connection or manage automatic re-authentication';

$string['reauthsettingssummary'] = 'Manage access credentials';
$string['reauthajaxfailed'] = 'The connection could not be renewed. Check the credentials and FredKI availability.';

$string['privacy:metadata:entitlement'] = 'A separate FredKI entitlement endpoint validates service access at bildungbleibt.de.';
$string['privacy:metadata:entitlement:licensekey'] = 'The configured FredKI service entitlement key.';
$string['privacy:metadata:entitlement:domain'] = 'The Moodle site hostname, excluding the scheme and path.';
$string['privacy:metadata:entitlement:plugin'] = 'The fixed plugin identifier mod_fredkicourseimport.';
