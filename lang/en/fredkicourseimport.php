<?php
// This file is part of Moodle - http://moodle.org/
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

$string['activitymappinglockedinfo'] = 'The mapping has already been set up and can no longer be changed. Create the activity again if needed.';
$string['agentcoursevisible'] = 'Show chatbot on course page';
$string['agentcoursevisible_help'] = 'When enabled, the FredKI embed script is output on the course page so the selected agent appears directly in the course.';
$string['agentid'] = 'FredKI agent';
$string['agentid_help'] = 'Select the FredKI agent here. Agents are loaded with the stored FredKI credentials.';
$string['agentsloaded'] = 'agents loaded.';
$string['agentsloaderror'] = 'Error loading agents.';
$string['agentsloading'] = 'Loading agents ...';
$string['apply'] = 'Apply';
$string['applytoselected'] = 'For selected files';
$string['authorizedby'] = 'Authorized by';
$string['authrenewbusy'] = 'Another request is renewing FredKI authorisation. Please try again.';
$string['autorenewactive'] = 'Automatic FredKI re-authentication is enabled. The plugin renews access tokens in the background when needed. Regular manual sign-in is not required.';
$string['autorenewinactive'] = 'Automatic re-authentication is not enabled yet. Sign in once and explicitly permit encrypted credential storage; afterwards tokens will be renewed automatically.';
$string['autorenewretained'] = 'Automatic re-authentication was previously enabled for this connection. It stays enabled while this checkbox remains selected.';
$string['availableagents'] = 'Available agents';
$string['availableknowledgebases'] = 'Available:';
$string['baseurl'] = 'FredKI base URL';
$string['chooseagent'] = 'Please select an agent';
$string['choosecontentforimport'] = 'Select course content and send to FredKI';
$string['chooseknowledgebase'] = 'Please select a knowledge base';
$string['cleanupcourseid'] = 'Original Moodle course ID';
$string['cleanupdocumentid'] = 'Remote document ID';
$string['cleanupempty'] = 'No remote document IDs have been registered for deleted activities.';
$string['cleanupknowledgeid'] = 'Knowledge base ID';
$string['cleanupregister'] = 'External FredKI documents – cleanup register';
$string['cleanupregistered'] = 'Recorded on';
$string['cleanupregisteropen'] = 'Review document identifiers saved after deleting a Courseimport activity';
$string['cleanupregisterwarning'] = 'This is a read-only list, not evidence that remote documents still exist. Check FredKI before removing anything. These entries are NOT deleted automatically. No passwords or access tokens are stored here. Display is limited to 1,000 newest entries.';
$string['cleanupworkspace'] = 'FredKI workspace';
$string['configureactivityfirst'] = 'Please first save FredKI username, password and knowledge base in this activity settings page.';
$string['confirmunselect'] = 'I confirm that deselected content will be removed from the FredKI knowledge base on synchronisation.';
$string['confirmunselectrequired'] = 'Previously published content has been deselected. Confirm its removal below the selection before continuing.';
$string['connectionoverview'] = 'Connection and target';
$string['contentmode_help'] = 'Complete transfers the contents provided by Moodle. For a glossary, each approved entry is indexed separately with its own title, definition and direct Moodle entry link. Reference transfers only the glossary title, description and overview link.';
$string['contentmodeknowledge'] = 'Complete';
$string['contentmodereference'] = 'Reference';
$string['coursesectiontexts'] = 'Course section texts';
$string['createddocumentscount'] = 'Created';
$string['debuglog'] = 'Enable debug logging';
$string['defaultactivityname'] = 'Send course content to FredKI';
$string['deleteddocumentscount'] = 'Deleted';
$string['deletionbatchblocked'] = 'Safety stop: {$a} deletions require review. The batch was not deleted. Contact the site administrator.';
$string['deselectall'] = 'None';
$string['deselectionapprovalexpired'] = 'The selection or remote document mappings changed after confirmation. Please review and confirm the updated list again.';
$string['deselectionconfirmbutton'] = 'Confirm removal and synchronise';
$string['deselectionremotecount'] = 'FredKI documents currently mapped to these sources: {$a}. A glossary can contain many documents per selected Moodle activity.';
$string['deselectionreviewheading'] = 'Confirm removal of selected content';
$string['deselectionreviewhint'] = 'Deselecting previously published content opens a separate confirmation page showing the affected sources and document count before synchronising.';
$string['deselectionreviewintro'] = 'You have deselected course content that was previously published. No upload or deletion has happened yet. Review the following sources; only these sources are authorised for removal from FredKI.';
$string['documentextractfailed'] = 'The document content could not be extracted by FredKI. The file may be encrypted, damaged, image-only, too large, or use an unsupported PDF/Office variant.';
$string['documenttype_book'] = 'Book';
$string['documenttype_folder'] = 'Folder';
$string['documenttype_glossary'] = 'Glossary';
$string['documenttype_h5p'] = 'H5P';
$string['documenttype_label'] = 'Text and media area';
$string['documenttype_lesson'] = 'Lesson';
$string['documenttype_material'] = 'Material';
$string['documenttype_page'] = 'Page';
$string['documenttype_resource'] = 'File';
$string['documenttype_scorm'] = 'SCORM';
$string['documenttype_section'] = 'Course section';
$string['documenttype_url'] = 'Link';
$string['entercredentialstoload'] = 'Please enter username and password first.';
$string['errordocumentscount'] = 'Errors';
$string['externalapierror'] = 'The FredKI service could not complete the request.';
$string['externalapiinvalidresponse'] = 'The FredKI service returned an invalid response.';
$string['filenotfound'] = 'File not found in Moodle.';
$string['files'] = 'Generated import files';
$string['fredkiagentheader'] = 'FredKI agent';
$string['fredkichaturl'] = 'FredKI chat URL';
$string['fredkichaturl_desc'] = 'Public chat URL of the selected FredKI agent. If FredKI does not provide the URL when loading agents, it can be pasted here manually.';
$string['fredkichaturl_help'] = 'Paste the public chat URL of the agent, e.g. https://fredkitest.lernsteine.com/chat/ff19702075bfdb0b. The plugin automatically generates the embed code from it.';
$string['fredkicourseimport:addinstance'] = 'Add a FredKI course import activity';
$string['fredkicourseimport:manage'] = 'Manage FredKI course import';
$string['fredkicourseimport:sync'] = 'Synchronize FredKI course import';
$string['fredkicourseimport:view'] = 'View FredKI course import';
$string['fredkidocumentid'] = 'FredKI document ID';
$string['fredkiembedauto'] = 'The embed code is generated automatically from the selected agent. The field is internal and does not need to be edited manually.';
$string['fredkiembedcode'] = 'FredKI embed code';
$string['fredkiembedcode_help'] = 'Paste the FredKI embed code here. It should start with <script async defer src="https://fredki.lernsteine.com/chat/api/embed?...">. The plugin only outputs FredKI embed scripts from the configured FredKI domain.';
$string['fredkipassword'] = 'FredKI password';
$string['fredkisettingsheader'] = 'FredKI access and knowledge base';
$string['fredkiusername'] = 'FredKI username';
$string['generateddocumentscount'] = 'Generated documents';
$string['generatedfilename'] = 'File';
$string['generatedfilepath'] = 'Area';
$string['generatedfilesize'] = 'Size';
$string['glossarydefinition'] = 'Definition';
$string['glossaryentries'] = 'Glossary entries';
$string['glossaryname'] = 'Glossary';
$string['hiddenforparticipants'] = 'Currently hidden for participants';
$string['importallcoursecontent'] = 'Send all course data to FredKI';
$string['importdone'] = 'Import completed. Created: {$a->created}, updated: {$a->updated}, unchanged: {$a->skipped}, deleted: {$a->deleted}. Processed: {$a->files} files, {$a->sectiontexts} section texts, {$a->activitytexts} activity descriptions, {$a->urls} links, {$a->glossaries} glossary entries, {$a->pages} pages, {$a->labels} text areas, {$a->books} book chapters, {$a->lessons} lessons, {$a->h5p} H5P guides, {$a->scorm} SCORM guides.';
$string['importrunning'] = 'Import running ...';
$string['includeactivitycontent'] = 'Transfer activity content';
$string['invalidbaseurl'] = 'The FredKI base URL must be a valid HTTPS address.';
$string['invalidcoursecontext'] = 'The course context could not be determined.';
$string['invalidglossaryentry'] = 'This glossary entry is no longer approved or cannot be verified. Please synchronize the sources again.';
$string['invalidjsonrequest'] = 'The request could not be processed.';
$string['invalidlicense'] = 'This plugin is not licensed for this Moodle site.';
$string['invalidlicensedomain'] = 'The Moodle domain could not be detected.';
$string['invalidlicensemissing'] = 'No license key has been configured for FredKI Course Data yet.';
$string['invalidlicenseserverempty'] = 'The license server did not respond.';
$string['invalidlicenseserverresponse'] = 'The license server returned an invalid response.';
$string['invalidrequestmethod'] = 'This request method is not allowed.';
$string['knowledgebase'] = 'Knowledge base';
$string['knowledgebasename'] = 'Knowledge base name';
$string['knowledgebasesloaded'] = 'knowledge bases loaded.';
$string['knowledgebasesloaderror'] = 'Error loading knowledge bases.';
$string['knowledgebasesloading'] = 'Loading knowledge bases ...';
$string['knowledgeid'] = 'Knowledge base ID';
$string['knowledgeidrequired'] = 'Please select a knowledge base.';
$string['lastsync'] = 'Last synchronization';
$string['license_required_for_activity'] = 'Please enter a valid license key in the plugin settings first. After that, knowledge bases can be loaded and activities can be used.';
$string['licenseblockmessage'] = 'FredKI functions are blocked because no valid license is available.';
$string['licenseconfigurefirst'] = 'Configure and validate the FredKI licence in the plugin settings before using the FredKI functions.';
$string['licensekey'] = 'License key';
$string['licensekey_desc'] = 'Enter the license key for this Moodle site.';
$string['loadagents'] = 'Load agents';
$string['loadknowledgebases'] = 'Load knowledge base';
$string['loginpath'] = 'FredKI login path';
$string['missingbaseurl'] = 'The FredKI base URL is not configured.';
$string['missingmoodlesource'] = 'Deleted course activity (not manually removed by this selection)';
$string['missingvalidtoken'] = 'FredKI authorisation has expired or was rejected. Open this CourseImport activity’s Settings and re-authorise the connection there. Your knowledge base is preserved.';
$string['modulename'] = 'FredKI course data';
$string['modulename_help'] = 'Imports existing course content directly to FredKI.';
$string['modulenameplural'] = 'FredKI course data';
$string['nofiles'] = 'No course content has been imported yet.';
$string['noknowledgebasechosen'] = 'No knowledge base selected yet.';
$string['nosyncentries'] = 'No entries in the sync table yet.';
$string['nothingselected'] = 'No content selected. Existing content will be removed from the FredKI knowledge base.';
$string['pendingdocumentscount'] = 'Pending';
$string['pluginadministration'] = 'Manage FredKI course data';
$string['pluginname'] = 'FredKI course data';
$string['privacy:metadata:activity'] = 'Authorising user for the import';
$string['privacy:metadata:activity:authorizedbyuserid'] = 'Moodle user ID originally authorised to operate this FredKI import.';
$string['privacy:metadata:auth'] = 'User-specific FredKI authorisation data.';
$string['privacy:metadata:auth:lastused'] = 'The last token use time.';
$string['privacy:metadata:auth:passwordencrypted'] = 'Optional encrypted password stored only when automatic re-authorisation is explicitly enabled.';
$string['privacy:metadata:auth:token'] = 'The secret FredKI access token.';
$string['privacy:metadata:auth:tokenexpires'] = 'The local token expiry time.';
$string['privacy:metadata:auth:userid'] = 'The Moodle user ID.';
$string['privacy:metadata:auth:username'] = 'The FredKI username.';
$string['privacy:metadata:entitlement'] = 'A separate FredKI entitlement endpoint validates service access at bildungbleibt.de.';
$string['privacy:metadata:entitlement:domain'] = 'The Moodle site hostname, excluding the scheme and path.';
$string['privacy:metadata:entitlement:licensekey'] = 'The configured FredKI service entitlement key.';
$string['privacy:metadata:entitlement:plugin'] = 'The fixed plugin identifier mod_fredkicourseimport.';
$string['privacy:metadata:fredki'] = 'FredKI is an external system to which selected Moodle course data is transferred.';
$string['privacy:metadata:fredki:browserdata'] = 'Browser connection data sent when the optional FredKI chat embed is loaded.';
$string['privacy:metadata:fredki:coursecontent'] = 'Course information selected by an authorised user.';
$string['privacy:metadata:fredki:password'] = 'The FredKI password used for authentication.';
$string['privacy:metadata:fredki:username'] = 'The FredKI username used for authentication.';
$string['privacy:metadata:fredkicourseimport'] = 'Configuration data for the FredKI course import activity.';
$string['processedcontentcount'] = 'Processed content';
$string['processingupload'] = 'Processing…';
$string['progressstarting'] = 'Preparing import ...';
$string['ready'] = 'Ready';
$string['reauthajaxfailed'] = 'The connection could not be renewed. Check the credentials and FredKI availability.';
$string['reauthbutton'] = 'Reconnect';
$string['reauthenable'] = 'Set up automatic re-authentication once';
$string['reauthheading'] = 'Reconnect FredKI';
$string['reauthhelp'] = 'Sign in again. The knowledge base and all imported documents remain unchanged.';
$string['reauthknowledgeinaccessible'] = 'This FredKI account cannot access the linked knowledge base. No changes have been made.';
$string['reauthlink'] = 'Re-authorise connection at any time';
$string['reauthmanage'] = 'Change FredKI credentials or manage automatic re-authentication';
$string['reauthmissingcredentials'] = 'Username and password are required.';
$string['reauthremember'] = 'With consent, encrypt and retain credentials for automatic token renewal (back up the Moodle server encryption key).';
$string['reauthsettingslabel'] = 'FredKI access';
$string['reauthsettingslink'] = 'Re-authorise the connection or manage automatic re-authentication';
$string['reauthsettingssummary'] = 'Manage access credentials';
$string['reauthsuccess'] = 'FredKI connection renewed successfully.';
$string['reauthwarning'] = 'FredKI authorisation has expired or was rejected. Open this CourseImport activity’s Settings to re-authorise FredKI access. Your existing knowledge base is preserved.';
$string['refreshdone'] = 'Changes updated. Created: {$a->created}, updated: {$a->updated}, unchanged: {$a->skipped}, deleted: {$a->deleted}.';
$string['selectall'] = 'All';
$string['selectcontentintro'] = 'Select which course content should be sent to FredKI. If you select nothing, all existing content from this activity will be removed from the FredKI knowledge base during the next synchronization.';
$string['selectedknowledgebase'] = 'Selected knowledge base:';
$string['selectedknowledgebasebutton'] = 'Selected';
$string['selection'] = 'Selection';
$string['selectknowledgebasebutton'] = 'Select';
$string['selectvisible'] = 'Visible for participants';
$string['skippeddocumentscount'] = 'Unchanged';
$string['sourcelabel'] = 'Text and media area';
$string['startselectedimport'] = 'Send selected content to FredKI';
$string['status_deleted'] = 'deleted';
$string['status_error'] = 'error';
$string['status_pending'] = 'pending';
$string['status_removed'] = 'deleted';
$string['status_synced'] = 'synchronized';
$string['syncalreadyrunning'] = 'A FredKI import is already running for this activity. Please try again when it has finished.';
$string['synceddocumentscount'] = 'Synced';
$string['syncprogress'] = 'Synchronization progress';
$string['syncrunning'] = 'Import is running. Please do not close this page.';
$string['syncrunningstep'] = 'Collecting course data and sending it to FredKI ...';
$string['syncstatus'] = 'Synchronization status';
$string['taskreconciledeleted'] = 'FredKI: reconcile sources physically deleted in Moodle';
$string['untitledglossaryentry'] = 'Untitled glossary entry';
$string['updatechanges'] = 'Update changes';
$string['updatechangesonly'] = 'Update changes only';
$string['updateddocumentscount'] = 'Updated';
