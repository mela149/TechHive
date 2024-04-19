<!DOCTYPE html>
<html>
<head>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Include DataTables CSS and JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <!-- Include DataTables Buttons CSS and JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.0.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.html5.min.js"></script>
    <!--<script src="https://cdn.datatables.net/fixedcolumns/3.3.4/js/dataTables.fixedColumns.min.js"></script> -->


    <?php echo '<link rel="stylesheet" type="text/css" href="' . $CFG->wwwroot . '/local/cohortreport/styles.css">'; ?>
    <style>
        /* Style for alternating row background colors */
        .stripe tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        /* Remove highlight of sorted column headers */
        #gradeTable tr.sorting_asc, #gradeTable tr.sorting_desc {
            background-color: initial !important;
        }

        /* Style for table header */
        #gradeTable thead th {
            background-color: #f8f9fa;
            color: black;
        }
    </style>
</head>
<body>

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
 * The Cohort groups grade report
 *
 * @package   local_cohortreport
 * @copyright 2023 Talha Mela <talhamela20000@hotmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_group\reportbuilder\local\entities\group;

 require_once(dirname(__FILE__) . '/../../config.php');
 require_once($CFG->libdir . '/gradelib.php');
 require_once($CFG->dirroot . '/user/renderer.php');
 require_once($CFG->dirroot . '/grade/lib.php');
 require_once($CFG->dirroot . '/grade/report/grader/lib.php');
 require_once($CFG->dirroot . '/grade/querylib.php');
 require_login();
 
 
 // Set up the page
 $PAGE->set_pagelayout('standard');
 $PAGE->set_title('Grader Report by Cohort');
 $PAGE->set_heading('Grader Report by Cohort');
 $PAGE->set_url(new moodle_url('/local/cohortreport/index.php'));

 // Output the header
 echo $OUTPUT->header();
 


 global $DB;
 


// SPLIT INTO 4 SECTIONS: 
// 1. FUNCTIONS
// 2. EXPORT TO EXCEL AND GROUP SELECT FORM
// 3. FILTERING COURSES AND STUDENT INFO
// 4. DISPLAYING RESULTS IN DATA TABLE



// SECTION 1: FUNCTIONS




 
// Get list of all group names
function get_all_group_names() {
    global $DB;

    $groups = $DB->get_records('groups');
    $group_names = array();

    foreach ($groups as $group) {
        $group_name = $group->name;
        $group_id = $group->id;

        // Initialize an array for this group name if not already present
        if (!isset($group_names[$group_name])) {
            $group_names[$group_name] = array();
        }

        // Check if the group ID is not already in the list for this group name
        if (!in_array($group_id, $group_names[$group_name])) {
            $group_names[$group_name][] = $group_id;
        }
    }

    return $group_names;
}


// Get all courses for a student which has groupmode set to "Seperate Groups"
function get_courses_for_student($userid) {
    global $DB;

    $contextsql = "SELECT c.id
    FROM {course}           AS c
    JOIN {context}          AS ctx ON c.id         = ctx.instanceid
    JOIN {role_assignments} AS ra  ON ra.contextid = ctx.id
    JOIN {user}            AS u   ON u.id         = ra.userid
    WHERE u.id = :useid
    AND ra.roleid = 5
    AND c.groupmode = 1";
    $contextparams = ['useid' => $userid];
    $contextids = $DB->get_records_sql($contextsql, $contextparams);

    $courses_student = array();
    foreach ($contextids as $contextid) {
        $courses_student [] = $contextid;
    }
    
    return $courses_student;
}

// Returns the selected group name's id for a course id
function get_group_id($courseid,$selected_group_name){
    global $DB;
    // Check if the global $DB is available
    if (!isset($DB)) {
        return '-';
    }

    $group = $DB->get_records('groups');
    $check = false;
    foreach($group as $g){
        if ($g->name === $selected_group_name){
            if($g->courseid === $courseid){
                $id = $g->id;
                $check = true;
            }
        }
    }
    if ($check === true){
        return $id;
    }
    else{
        return '';
    }
}

// Old function that doesn't take grade categores into account
function get_user_grade_for_course1($userid, $courseid) {
    global $DB;

    // Check if the global $DB is available
    if (!isset($DB)) {
        return '-';
    }

    // Fetch the grade for the specified user and course
    $sql = "SELECT ROUND((SUM((gg.finalgrade / gg.rawgrademax) * gi.aggregationcoef2)*100)/SUM(gi.aggregationcoef2),2) AS OverallPercentage
    FROM {user} AS u
    JOIN {grade_grades} AS gg ON u.id = gg.userid
    JOIN {grade_items} AS gi ON gg.itemid = gi.id
    JOIN {course} AS c ON gi.courseid = c.id
    WHERE gg.userid = :userid
    AND gi.courseid = :courseid
    AND gg.finalgrade IS NOT NULL";

    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
    ];

    $grade = $DB->get_field_sql($sql, $params);
    $studentcourses = get_courses_for_student($userid);
    $condition = false;

    foreach ($studentcourses as $course){
        if ($course ->id === $courseid){
            $condition = true;
            break;
        }
    }
    if ($condition === false){
        $grade = "Not enrolled";
    }

    // If a grade is found, return it; otherwise, return ' '
    return ($grade !== false) ? $grade : ' ';
}

// Calculates the overall percentage grade by taking weights and grade subcategory weights into account
function get_user_grade_for_course($userid, $courseid){
    global $DB;

    // Check if the global $DB is available
    if (!isset($DB)) {
        return '-';
    }

    // Check if the course has child grade categories
    $sqlCheckCategories = "SELECT COUNT(DISTINCT cg.id) AS category_count
        FROM {grade_categories} cg
        WHERE cg.courseid = :courseid
        AND cg.parent IS NOT NULL";

    $paramsCheckCategories = [
        'courseid' => $courseid,
    ];

    $hasCategories = $DB->get_field_sql($sqlCheckCategories, $paramsCheckCategories);
    if ($hasCategories<1){

        // Fetch the grade for the specified user and course
        $sql = "SELECT ROUND((SUM((gg.finalgrade / gg.rawgrademax) * gi.aggregationcoef2)*100)/SUM(gi.aggregationcoef2),2) AS OverallPercentage
        FROM {user} AS u
        JOIN {grade_grades} AS gg ON u.id = gg.userid
        JOIN {grade_items} AS gi ON gg.itemid = gi.id
        JOIN {course} AS c ON gi.courseid = c.id
        WHERE gg.userid = :userid
        AND gi.courseid = :courseid
        AND gg.finalgrade IS NOT NULL";

        $params = [
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        $grade = $DB->get_field_sql($sql, $params);
        $studentcourses = get_courses_for_student($userid);
        $condition = false;

        foreach ($studentcourses as $course){
            if ($course ->id === $courseid){
                $condition = true;
                break;
            }
        }
        if ($condition === false){
            $grade = "Not enrolled";
        }
        elseif (is_numeric($grade)) {
            // Format the grade with two decimal places and add a percentage sign
            $grade = number_format($grade, 2) . '%';
        }
        else {
            $grade = "In progress";
        }

        // If a grade is found, return it; otherwise, return 'In progress'
        return ($grade !== false) ? $grade  : 'In progress';}

    else{
        
        
        global $DB;

        // Check if the global $DB is available
        if (!isset($DB)) {
           return '-';
        }
        $studentcourses = get_courses_for_student($userid);
        $condition = false;

        foreach ($studentcourses as $course){
            if ($course ->id === $courseid){
                $condition = true; // If the student takes the course the condition = true
                break;
            }
        }
        if ($condition === true){ // If student does take the course
            $sql = "SELECT  gi.id, gg.rawgrademax,gg.finalgrade, gi.aggregationcoef2, gi.categoryid, gc.parent
            FROM {user} AS u
            JOIN {grade_grades} AS gg ON u.id = gg.userid
            JOIN {grade_items} AS gi ON gg.itemid = gi.id
            JOIN {grade_categories} AS gc ON gi.categoryid = gc.id
            JOIN {course} AS c ON gi.courseid = c.id
            WHERE gg.userid = :userid
            AND gi.courseid = :courseid
            AND gg.finalgrade IS NOT NULL";

        
            $params = [
                'userid' => $userid,
                'courseid' => $courseid,
            ];
            $info = $DB->get_records_sql($sql, $params);
            $sql2 = "SELECT gc.id, gi.aggregationcoef2
            FROM {grade_grades} as gg
            JOIN {grade_items} as gi ON gg.itemid = gi.id
            JOIN {course} as c ON gi.courseid = c.id
            JOIN {grade_categories} as gc ON c.id = gc.courseid
            WHERE c.id = :courseid
            AND gg.userid = :userid
            AND gi.itemtype = 'category'
            AND gc.parent != ''";
            $child_categories = $DB->get_records_sql($sql2,$params);
            
            $totalgrade = 0;
            $totalaggregate = 0;
            $counter = 0;
            foreach($info as $i){
                $counter += 1;
                $rawgrademax = $i -> rawgrademax;
                $finalgrade = $i -> finalgrade;
                $aggcoef2 = $i -> aggregationcoef2;
                $cat = $i -> categoryid;
                $parent = $i -> parent;
                if ($parent != NULL || $parent != ''){
                    foreach($child_categories as $child){
                        $id = $child->id;
                        $agg = $child->aggregationcoef2;
                        if ($id == $cat){
                            $aggcoef2 = $aggcoef2 * $agg;
                        }
                    }
                }
                $grade_out_of_one = $finalgrade/$rawgrademax;
                $weightedgrade = $grade_out_of_one * $aggcoef2 *100;
                $totalgrade += $weightedgrade; // The sum of the weighted grades for each grade item a student has attempted
                $totalaggregate += $aggcoef2;
            }
            if ($counter != 0){
                    $grade = $totalgrade/$totalaggregate; // Divided by total agreggate to get the grade out of the activities attempted
                    $grade = round($grade,2);
                    if (is_numeric($grade)) {
                        // Format the grade with two decimal places and add a percentage sign
                        $grade = number_format($grade, 2) . '%';
                    }
                }
                else {
                    $grade = 'In progress';
                }
            
        }
        else{
                $grade = "Not enrolled";
        }

        // If a grade is found, return it; otherwise, return ' '
        return ($grade !== false) ? $grade : 'In progress';
    }
    //return 0;
}

// Function used in Cohort report 2 (Detailed)
function calculateDurationInWeeksAndDays($timestart, $timeend) {
    // Calculate the duration in seconds
    if ($timestart == 0 and $timeend == 0){
        $time = "Not Applicable";
        return $time;
    }
    $durationInSeconds = $timeend - $timestart;
    // Calculate the number of weeks
    $weeks = floor($durationInSeconds / (7 * 24 * 3600));

    // Calculate the remaining seconds after removing the weeks
    $remainingSeconds = $durationInSeconds % (7 * 24 * 3600);

    // Calculate the number of days from the remaining seconds
    $days = floor($remainingSeconds / (24 * 3600));
    if ($durationInSeconds < 0){
        $time = "Ongoing";
    }
    else{
        $time = $weeks." weeks, ".$days." days"; 
    }
    
    return $time;
}




// Function used in Cohort report 2 (Detailed) 
function get_dates($userid, $courseid){
    global $DB;
    $sql = "SELECT ue.timestart AS uenroltimestart, ue.timecreated AS uenroltimecreated, e.timecreated AS enroltimecreated, ue.timeend AS uetimeend
    FROM {enrol} AS e
    JOIN {course} AS c ON e.courseid = c.id
    JOIN {user_enrolments} AS ue ON e.id = ue.enrolid
    WHERE c.id = :courseid
    AND ue.userid = :userid
    AND e.roleid = 5
    ";

    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
    ];
    $dates = $DB->get_records_sql($sql,$params);
    $sql = "SELECT u.id, cc.timecompleted AS timecomplete
    FROM {role_assignments}   AS ra
    JOIN {context}            AS ctx ON ctx.id    = ra.contextid   AND ctx.contextlevel = 50
    JOIN {course}             AS c   ON c.id      = ctx.instanceid 
    JOIN {user}               AS u   ON u.id      = ra.userid
    JOIN {course_completions} AS cc  ON cc.course = c.id           AND cc.userid        = u.id
    WHERE u.id = :userid
    AND c.id = :courseid";
    $completedate = $DB->get_field_sql($sql, $params);
    foreach ($dates as $key=>$value){
        $startdate = $value->uenroltimecreated;
        $enddate = $value->uetimeend;
    }
    foreach ($completedate as $key=>$value){
        if ($value->timecomplete != "" or $value->timecomplete != 0){
            $enddate = $value->timecomplete;
        }
    }
    $duration = calculateDurationInWeeksAndDays($startdate, $enddate);
    if ($enddate == 0){
        $enddate = "Not Ended";
    }
    else {
        $enddate = date('Y-m-d H:i:s', $enddate);
    }
    if ($startdate == ""){
        $startdate = "No start date";
    }
    else{
       $startdate = date('Y-m-d H:i:s', $startdate); 
    }

    $end = ['startdate'=>$startdate, 'enddate'=>$enddate, 'duration'=>$duration];
    return $end;

}



//END OF SECTION 1: FUNCTIONS



// SECTION 2: EXPORT TO EXCEL AND GROUP SELECT FORM



$group_names = get_all_group_names();

// Display the dropdown menu
echo '<form method="get" id="group-select-form" class="form-inline">';
echo '<label for="group">Select a Cohort:</label>';
echo '<select name="group" id="group" class="custom-select singleselect">';


// Loop through group names and add options
foreach ($group_names as $groupname => $groupvalues) {
    $option_selected = ($groupname == $_GET['group']) ? 'selected' : '';
    echo "<option value='$groupname' $option_selected>$groupname</option>";
}


// Submit button and Export to Excel button
echo '</select>';
echo '<div class="flex-fill d-flex "><input type="submit" value="Submit" id="submit-button" class="btn btn-primary"></div>';
echo '<button id="customExportButton" class="btn btn-primary" type="button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
<path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z"/>
</svg>Export to Excel</button>';
echo '</form>';


if (isset($_GET['group'])) {
    $selected_group_name = $_GET['group']; // Get the selected group name from the dropdown
    // Use $selected_group_name for further processing
}
// Determine the selected group name 
$selectedGroupName = $selected_group_name; 

// Echo the selected group name as a JavaScript variable for exported excel sheet name
echo '<script>var selectedGroupName = "' . $selectedGroupName . '";</script>';


// END OF SECTION 2: EXPORT BUTTON AND GROUP SELECT FORM




// SECTON 3: FILTERING COURSES AND STUDENT INFO




$group_names = get_all_group_names();


$selected_group_ids = array();
foreach ($group_names as $key => $value){
    if ($key === $selected_group_name){
        $selected_group_ids = $value;
    }
}



$users = $DB->get_records('user'); // ALL users
$groupmembers = array(); //Group member non repeated that are in the selected group
$group_member = $DB->get_records('groups_members'); //ALL group members
$group_member_ids = array(); // The non-repeated ids of the users who are in groups

foreach($group_member as $member){
    $id = $member -> userid;
    if (!in_array($id, $group_member_ids)){
        $group_member_ids [] = $id;
    }
}


foreach ($group_member as $member ){
    if (in_array($member -> groupid, $selected_group_ids) ){ //for each member in all group members there is a member with a group id that is in the selected group ids
        $id = $member -> userid;
        $i = true;
        foreach ($groupmembers as $mem){
            $memid = $mem -> userid;
            if ($memid === $id){
                $i = false;
                break;
            }
        }
        if ($i === true){
            $groupmembers[] = $member;
        }
    }
}






$selectedusers = array();  // Now we have info of each user who is in a group. (Duplicates removed)
foreach ($users as $user){
    foreach ($groupmembers as $member){
        if ($user -> id === $member -> userid){
            $role = $DB->get_record('role_assignments',['userid' => $user->id]);
            if ($role->roleid == '5'){
                $selectedusers[] = $user;
            }
        }
    }
}



//Group courses
$courses = $DB->get_records('course', ['groupmode' => 1]);



$courseslist = array(); // First list of courses (to be filtered further to not include course managers)
$courseslist1 = array(); // Dummy array
foreach ($selectedusers as $use){
    $courses_list_student = get_courses_for_student($use -> id);
    foreach($courses_list_student as $course=>$id){
        if(!in_array($id , $courseslist1)){
            $courseslist1 [] = $id;
        }
    }
}
foreach ($courseslist1 as $course=>$value){
    $val = $value->id;
    $coursedetail = $DB->get_record('course',['id'=>$val] );
    $courseslist [] = $coursedetail; // To get complete course objects with every field
}



foreach ($courseslist as $course){
    if ($selected_group_name === "ALL"){ // ALL option was not displaying table for 10x site but was for meetload and localhost so was discontinued as issue was ambiguous
        $course->groupid = 0;
    }
    else {
        $id = get_group_id($course->id,$selected_group_name);
        $course->groupid = $id; // Adding the group id for the selected group name to each course object
    }
}

$selectedusers1 = array();

foreach ($courseslist as $course){
    foreach ($selectedusers as $user){
        if($selected_group_name != 'ALL'){
            $sql = "SELECT ra.roleid 
            FROM {course} AS c
            JOIN {context} AS ctx ON c.id = ctx.instanceid
            JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
            JOIN {user} AS u ON u.id = ra.userid
            JOIN {groups_members} AS gm ON gm.userid = u.id
            JOIN {groups} AS g ON g.id = gm.groupid
            WHERE ra.userid = :userid
			AND g.name = :groupname
			AND c.id = :courseid
			AND g.courseid = :courseid1";
            $params = [
                'userid' => $user->id,
                'courseid' => $course->id,
                'courseid1' => $course->id,
                'groupname' => $selected_group_name,
            ];
            $info = $DB->get_field_sql($sql, $params);
            if ($info == 5){ // Filtering out if a student has roleid = 5 in each course
                if (!in_array($user->id, $selectedusers1)){
                    $selectedusers1[$user->id][] = $user;
                }
            }
        }
        else{
            $sql = "SELECT ra.roleid 
            FROM {course} AS c
            JOIN {context} AS ctx ON c.id = ctx.instanceid
            JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
            JOIN {user} AS u ON u.id = ra.userid
            JOIN {groups_members} AS gm ON gm.userid = u.id
            JOIN {groups} AS g ON g.id = gm.groupid
            WHERE u.id = :userid
            AND g.id IS NOT NULL
            AND ra.userid = :userid1
            AND c.id = :courseid
            AND g.courseid = :courseid1";
            $params = [
                'userid' => $user->id,
                'courseid' => $course->id,
                'userid1' => $user->id,
                'courseid1' => $course->id,
            ];
            $info = $DB->get_field_sql($sql, $params);
            if ($info == 5){
                if (!in_array($user->id, $selectedusers1)){
                    $selectedusers1[$user->id][] = $user;
                }
            }
        }
    }
}
$selectedusers2 = array();
foreach($selectedusers1 as $user=>$rest){
    foreach($selectedusers as $user1){
        if($user ==$user1->id){
            $selectedusers2[] = $user1;
        }
    }
}
$selectedusers = $selectedusers2; // Used filtering one more time to obtain finer results that included all relevant courses but not users who are managers in any of the courses

$courseslist2 = array();
$courseslist3 = array();
foreach ($selectedusers as $use){
    $courses_list_student = get_courses_for_student($use -> id);
    foreach($courses_list_student as $course=>$id){
        if(!in_array($id , $courseslist3)){
            $courseslist3 [] = $id;
        }
    }
}
foreach ($courseslist3 as $course=>$value){
    $val = $value->id;
    $coursedetail = $DB->get_record('course',['id'=>$val] );
    $courseslist2 [] = $coursedetail;
}
$courseslist4 = array();

foreach ($courseslist as $course){
    foreach ($courseslist2 as $course1){
        if ($course->id == $course1->id){
            $courseslist4[] = $course;
        }
    }
}
$courseslist= $courseslist4; // Filtering again for finer and perfect results




// END OF SECTION 3: FILTERING STUDENT AND COURSES INFO




// SECTION 4: DISPLAYING RESULTS IN DATA TABLE





// Output the results
echo '<table id="gradeTable" border="1" >';
echo '<thead><tr><th id="namecolumn">First name/Last name</th>';
//echo '<th id= "emailcolumn">Email address</th>'; // For email addresses column
foreach ($courseslist as $course) { // For each course's column with hyperlink for the current group selected
    if ($course->groupid != ''){
        echo '<th><a href="/grade/report/grader/index.php?id=' . $course->id . '&group='.$course->groupid.'">' . $course->shortname . '</a></th>';
    }
}
echo '</tr></thead>';

echo '<tbody>';
foreach ($selectedusers as $selecteduser) {
    echo '<tr>';
    echo '<td id="sticky-column"><a href="/user/profile.php?id=' . $selecteduser->id . '">' . $selecteduser->firstname . " " . $selecteduser->lastname . '</a></td>';
    //echo '<td>' . $selecteduser->email . '</td>';

    foreach ($courseslist as $course) {
        if ($course->groupid != ''){
            echo '<td>';
            $grade = get_user_grade_for_course($selecteduser->id, $course->id);
            echo $grade;
            echo '</td>';
        }
    }

    echo '</tr>';
}
echo '</tbody>';

echo '</table>';

echo '</div>';



// Output the footer
echo $OUTPUT->footer();
?>

<script>
$(document).ready(function() {
    var dataTable = $('#gradeTable').DataTable({
        dom: '<"datatable-header"lBf>t<"datatable-footer"ip>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Export to Excel',
                filename: function() {
                    var currentDatetime = new Date();
                    var timestamp = currentDatetime.toISOString().slice(0, 19).replace(/:/g, '-');
                    return 'Grade report - Group: ' + selectedGroupName + ' ' + timestamp;
                }
            }
        ]
    });

    $('#customExportButton').click(function() {
        dataTable.button('.buttons-excel').trigger();
    });
    $("[name='gradeTable_length']").addClass('custom-select singleselect');
    //$("[name='gradeTable_length']").css('width', 64);
    

});
</script>


</body>
</html>