<?php

/**
 * @package   mod_matrix
 * @copyright 2020, New Vector Ltd (Trading as Element)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    // Notes:
    // * We use internal:false to listen *after* the DB transaction completes.
    // * We listen to pretty much anything that will affect our state of the Matrix rooms.
    // * If anything in this file changes, bump the version number.

    array(
        'eventname' => '\core\event\group_member_added',
        'callback' => '\mod_matrix\observer::observe_group_member_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\group_member_removed',
        'callback' => '\mod_matrix\observer::observe_group_member_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\group_created',
        'callback' => '\mod_matrix\observer::observe_group_created',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\role_assigned',
        'callback' => '\mod_matrix\observer::observe_role_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\mod_matrix\observer::observe_role_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\role_capabilities_updated',
        'callback' => '\mod_matrix\observer::observe_role_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\role_deleted',
        'callback' => '\mod_matrix\observer::observe_role_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\mod_matrix\observer::observe_enrolment_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\mod_matrix\observer::observe_enrolment_change',
        'internal' => false,
    ),
    array(
        'eventname' => '\core\event\user_enrolment_updated',
        'callback' => '\mod_matrix\observer::observe_enrolment_change',
        'internal' => false,
    ),
);
