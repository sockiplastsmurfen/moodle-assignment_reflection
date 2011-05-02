<?php

require("../../../../config.php");
require("../../lib.php");
require("assignment.class.php");

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

if (!$cm = get_coursemodule_from_id('assignment', $id)) {
    error("Course Module ID was incorrect");
}

if (!$assignment = get_record("assignment", "id", $cm->instance)) {
    error("Assignment ID was incorrect");
}

if (!$course = get_record("course", "id", $assignment->course)) {
    error("Course is misconfigured");
}

if (!$user = get_record("user", "id", $userid)) {
    error("User is misconfigured");
}

require_login($course->id, false, $cm);

if (($USER->id != $user->id) && !has_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id))) {
    error("You can not view this assignment");
}

if ($assignment->assignmenttype != 'reflection') {
    error("Incorrect assignment type");
}

$assignmentinstance = new assignment_reflection($cm->id, $assignment, $cm, $course);

if ($submission = $assignmentinstance->get_submission($user->id)) {
    print_header(fullname($user, true) . ': ' . $assignment->name);

    print_heading(get_string('typereflection', 'assignment_reflection'), 'center', 3);
    print_simple_box(format_text($submission->data1, $submission->format), 'center', '100%');

    print_simple_box_start('center', '', '', '', 'generalbox', 'dates');
    echo '<table>';
    if ($assignment->timedue) {
        echo '<tr><td class="c0">' . get_string('duedate', 'assignment') . ':</td>';
        echo '    <td class="c1">' . userdate($assignment->timedue) . '</td></tr>';
    }
    echo '<tr><td class="c0">' . get_string('lastedited') . ':</td>';
    echo '    <td class="c1">' . userdate($submission->timemodified);
    /// Decide what to count
    if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_WORDS) {
        echo ' (' . get_string('numwords', '', count_words(format_text($submission->data1, $submission->format))) . ')</td></tr>';
    } else if ($CFG->assignment_itemstocount == ASSIGNMENT_COUNT_LETTERS) {
        echo ' (' . get_string('numletters', '', count_letters(format_text($submission->data1, $submission->format))) . ')</td></tr>';
    }
    echo '</table>';
    print_simple_box_end();


    $obj = unserialize($submission->data2);

    $sql = 'SELECT id, discussion, parent, subject, message, format, userid
                FROM ' . $CFG->prefix . 'forum_posts
                WHERE userid = ' . $userid . '
                AND parent != 0
                AND discussion IN (
                    SELECT id
                    FROM ' . $CFG->prefix . 'forum_discussions
                    WHERE forum = ' . $obj->forumid . ')';

    $arr = get_records_sql($sql);

    print_heading(get_string('reflectioncomments', 'assignment_reflection', $arr ? count($arr) : 0), 'center', 3);

    if ($arr) {
        foreach ($arr as $obj) {
            $comment = '<a href="' . $CFG->wwwroot . '/mod/forum/discuss.php?d=' . $obj->discussion . '#p' . $obj->id . '">' .
                    format_text($obj->subject, $obj->format) . '</a>' . format_text($obj->message, $obj->format);
            print_simple_box($comment, 'center', '100%');
        }
    }
    close_window_button();
    print_footer('none');
} else {
    print_string('emptysubmission', 'assignment');
}
?>
