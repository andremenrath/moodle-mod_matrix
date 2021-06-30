<?php

/**
 * @package   mod_matrix
 * @copyright 2020, New Vector Ltd (Trading as Element)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
 * @package   mod_matrix
 * @copyright 2020, New Vector Ltd (Trading as Element)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require '../../config.php';

require_once 'lib.php';

$id = required_param('id', PARAM_INT);
 [$course, $cm] = get_course_and_cm_from_cmid($id, 'matrix');
$matrix = $DB->get_record('matrix', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$PAGE->set_url('/mod/matrix/view.php', ['id' => $cm->id]);
$PAGE->set_title($matrix->name);
$PAGE->set_cacheable(false);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if (!has_capability('mod/matrix:view', $PAGE->context)) {
    echo $OUTPUT->confirm(
        sprintf(
            '<p>%s</p>%s',
            get_string(isguestuser() ? 'view_noguests' : 'view_nojoin', plugin::COMPONENT),
            get_string('liketologin')
        ),
        get_login_url(),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
    echo $OUTPUT->footer();

    exit;
}

$possible_rooms = $DB->get_records('matrix_rooms', ['course_id' => $matrix->course]);

if (count($possible_rooms) == 0) {
    echo '<div class="alert alert-danger">' . get_string('vw_error_no_rooms', 'matrix') . '</div>';
    echo $OUTPUT->footer();

    exit;
}

$groups = groups_get_all_groups($matrix->course, 0, 0, 'g.*', true);

if (count($groups) > 0) {
    $visible_groups = groups_get_activity_allowed_groups($cm);

    if (empty($visible_groups)) {
        echo '<div class="alert alert-danger">' . get_string('vw_error_no_rooms', 'matrix') . '</div>';
        echo $OUTPUT->footer();

        exit;
    } elseif (count($visible_groups) == 1) {
        $group = reset($visible_groups);
        $room = $DB->get_record('matrix_rooms', ['course_id' => $matrix->course, 'group_id' => $group->id]);

        if (!$room) {
            echo '<div class="alert alert-danger">' . get_string('vw_error_no_rooms', 'matrix') . '</div>';
            echo $OUTPUT->footer();

            exit;
        }
        $room_url = json_encode(matrix_make_room_url($room->room_id));
        echo '<script type="text/javascript">window.location = ' . $room_url . ';</script>';
        echo '<a href="' . $room_url . '">' . get_string('vw_join_btn', 'matrix') . '</a>';
        echo $OUTPUT->footer();

        exit;
    }

    // else multiple groups are possible

    // ... unless there's only one possible option anyways
    if (count($possible_rooms) == 1) {
        $room_url = json_encode(matrix_make_room_url(reset($possible_rooms)->room_id));
        echo '<script type="text/javascript">window.location = ' . $room_url . ';</script>';
        echo '<a href="' . $room_url . '">' . get_string('vw_join_btn', 'matrix') . '</a>';
        echo $OUTPUT->footer();

        exit;
    }

    echo '<div class="alert alert-warning">' . get_string('vw_alert_many_rooms', 'matrix') . '</div>';

    foreach ($visible_groups as $id => $group) {
        $room = $DB->get_record('matrix_rooms', ['course_id' => $matrix->course, 'group_id' => $group->id]);

        if (!$room) {
            continue;
        }

        $name = groups_get_group_name($group->id);
        $room_url = json_encode(matrix_make_room_url($room->room_id));
        echo '<p><a href="' . $room_url . '">' . $name . '</a></p>';
    }
}

echo $OUTPUT->footer();
