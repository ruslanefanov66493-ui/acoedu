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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dashboard layout override for LearnHub theme.
 *
 * @package   theme_learnhub
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

$extraclasses = [];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);
$currentpath = $PAGE->url->get_path();
$loggedin = isloggedin() && !isguestuser();

$userinitials = '';
$displayname = '';
if ($loggedin) {
    $firstname = trim($USER->firstname ?? '');
    $lastname = trim($USER->lastname ?? '');
    $displayname = ($firstname !== '' && $firstname === $lastname) ? $firstname : fullname($USER);
    $firstnameinitial = core_text::substr($firstname, 0, 1);
    $lastnameinitial = ($firstname === $lastname) ? '' : core_text::substr($lastname, 0, 1);
    $userinitials = trim(core_text::strtoupper($firstnameinitial . $lastnameinitial));
    if ($userinitials === '') {
        $userinitials = 'U';
    }
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'headercontent' => $headercontent,
    'overflow' => $overflow,
    'addblockbutton' => $addblockbutton,
    'wwwroot' => $CFG->wwwroot,
    'loggedin' => $loggedin,
    'username' => $displayname,
    'userinitials' => $userinitials,
    'homeurl' => $CFG->wwwroot . '/',
    'myurl' => $CFG->wwwroot . '/my/',
    'mycoursesurl' => $CFG->wwwroot . '/my/courses.php',
    'meetingsurl' => $CFG->wwwroot . '/theme/learnhub/meetings.php',
    'ismeetings' => ($currentpath === '/theme/learnhub/meetings.php'),
    'profileurl' => $CFG->wwwroot . '/user/profile.php',
    'logouturl' => $CFG->wwwroot . '/login/logout.php?sesskey=' . sesskey(),
    'acologo' => (new moodle_url('/theme/learnhub/pix/aco-logo.png'))->out(false),
    'isdashboard' => ($currentpath === '/my/' || $currentpath === '/my/index.php'),
    'ismycourses' => ($currentpath === '/my/courses.php'),
];

echo $OUTPUT->render_from_template('theme_learnhub/mydashboard', $templatecontext);
