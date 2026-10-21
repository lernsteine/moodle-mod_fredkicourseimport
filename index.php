<?php
/**
 * Course activity overview entry point for FredKI Courseimport.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
\core_courseformat\activityoverviewbase::redirect_to_overview_page($courseid, 'fredkicourseimport');
