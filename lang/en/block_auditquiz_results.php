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
 * Strings for component 'block_course_list', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   block_auditquiz_results
 * @copyright 2015 Valery Fremaux
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['auditquiz_results:addinstance'] = 'Add a new audit block';
$string['auditquiz_results:myaddinstance'] = 'Add a new audit quiz block to My home';
$string['auditquiz_results:seeother'] = 'Can see other people results';
$string['auditquiz_results:export'] = 'Can export reports';

// Privacy.
$string['privacy:metadata'] = 'The Audit Quiz Results block does not directly store any personal data about any user.';

$string['addcourses'] = 'Add courses';
$string['applytofailed'] = 'You need apply to';
$string['applytofailed_desc'] = 'You have no results on this category, this course will complete your knowledge and help you improve your results on this topic';
$string['applytoinsufficiant'] = 'You need apply to';
$string['applytoinsufficiant_desc'] = 'You have not sufficiant results on this category, this course will complete your knowledge and help you improve your results on this topic';
$string['applytoregular'] = 'You should apply to';
$string['applytoregular_desc'] = 'You are regular on this topic. Maybe could you reinforce your knowledge and make it stronger on this topic.';
$string['applytosuccess'] = 'You can apply to';
$string['applytosuccess_desc'] = 'You have passed the topic. You may apply to this course if you are interested in looking how we presented the topic.';
$string['ariaviewfilteroption'] = '{$a} filter option';
$string['backtocourse'] = 'Back to course';
$string['back'] = 'Back to assignation map';
$string['bycategory'] = 'By category';
$string['byuser'] = 'By user';
$string['byname'] = 'By name';
$string['byscore'] = 'By score';
$string['configblockquizmodules'] = 'Installed quiz modules';
$string['catname'] = 'Category';
$string['configblockquizmodules_desc'] = 'Choose the module types that are known being reportable quizzes in this Moodle. (Use a comma separated list).';
$string['configenablecoursemapping'] = 'Enable course mapping';
$string['configdisablesuspendedenrolments'] = 'Disable suspended enrolements';
$string['configdisablesuspendedenrolments_desc'] = 'If set, suspended users will not be shown nor compiled.';
$string['configgraphsize'] = 'Graph dimension';
$string['configgraphtype'] = 'Graph type';
$string['configlayout'] = 'Publish data ';
$string['configpassrate1'] = 'Pass threshold 1';
$string['configpassrate2'] = 'Pass threshold 2';
$string['configpassrate3'] = 'Pass threshold 3';
$string['configproposeenrolonsuccess'] = 'Propose enrol on success';
$string['configquiztype'] = 'Quiz type';
$string['configselectquiz'] = 'Quiz instance';
$string['configstudentcanseeown'] = 'Student can see his results';
$string['confignoquizzesincourse'] = 'No quizes in this course';
$string['coursebindings'] = 'Course bindings';
$string['coursereport'] = 'Course report';
$string['detail'] = 'Detail';
$string['enrolmethods'] = 'Manage course enrol methods';
$string['enrolusers'] = 'Enrol users';
$string['erroremptyquizrecord'] = 'This quiz module seems not exisiting in database';
$string['errornojqplot'] = 'JQPlot is not installed in this Moodle. Please contact administrator.';
$string['errornonexistantcoursemodule'] = 'The configured course module is not existant or may have been deleted.';
$string['errornoquestions'] = 'The quiz choosen seems having no evaluated questions.';
$string['errornocategories'] = 'No categories';
$string['errornoquiz'] = 'There is no quiz in this course this block might monitor progress on';
$string['errornoquizselected'] = 'No quiz to report on. Please select one.';
$string['extcourses'] = 'Assigned courses';
$string['ezrorbadscale'] = 'Pass rate 2 must be higher than pass rate, or empty to disable';
$string['gotocoursereport'] = 'Course report';
$string['height'] = 'Graph height';
$string['linkedcourses'] = 'Proposed courses';
$string['makesnapshot'] = 'Make a snapshot';
$string['mapcategories'] = 'Map categories';
$string['mapcourses'] = 'Map courses';
$string['maxscore'] = 'Max score';
$string['nocourses'] = 'No course associated.';
$string['nousers'] = 'No applicable users';
$string['noquestions'] = 'No usable questions';
$string['noresultsyet'] = 'You have no results on this quiz. Nothing can be reported yet.';
$string['pass'] = 'Pass rate: {$a} ';
$string['lowpass'] = 'Low rate: {$a} ';
$string['midpass'] = 'Regular rate: {$a} ';
$string['highpass'] = 'Success rate: {$a} ';
$string['passrate'] = 'Pass rate';
$string['passrate2'] = 'Pass rate 2';
$string['passrate3'] = 'Pass rate 3';
$string['pluginname'] = 'Audit Quiz Results';
$string['potcourses'] = 'Potential courses';
$string['potcoursesmatching'] = 'Potential courses match';
$string['publishinblock'] = 'in the block space';
$string['fullpublishinblock'] = 'Full view in the block space';
$string['publishinpage'] = ' in separate page';
$string['quizid'] = 'Knowledge Auditing Quizzes';
$string['rate'] = 'Rate';
$string['results'] = 'Results';
$string['snapshots'] = 'Snapshots';
$string['score'] = 'My score';
$string['time'] = 'Date based progress line';
$string['unlinkcourse'] = 'Unlink course';
$string['viewresults'] = 'View result page';
$string['width'] = 'Graph width';

$string['quizid_help'] = 'Choose one or several quizzes that build the audit test.';

$string['configpassrate1_help'] = 'If set, pass rate will discriminate passed courses from failed courses. It must
be set between 0 and 100 (percent) of the category max score.';

$string['configpassrate2_help'] = 'If set, pass rate 2 adds an assessment level. If used, the result will be split
in three subsets : failed, regular (passed first rate, but rate 2 failed), and passed (above both) courses. It
must be set between 0 and 100 (percent) of the category max score.';

$string['configpassrate3_help'] = 'If set, pass rate 3 adds an assessment level. If used, the result will be split
in four subsets : failed, insuficiant (passed first rate, but rate 2 failed), regular (passed rate 2, but rate 3 failed) and passed (above rate 3) courses. It
must be set between 0 and 100 (percent) of the category max score.';

$string['assignablecourses_desc'] = 'You can choose any course in Moodle having a "self" enrol method to be assigned
to a question category, however, only courses with an active enrol will be really presented to users.';

include(__DIR__.'/pro_additional_strings.php');
