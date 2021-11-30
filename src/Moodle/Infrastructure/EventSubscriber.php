<?php

declare(strict_types=1);

/**
 * @package   mod_matrix
 * @copyright 2020, New Vector Ltd (Trading as Element)
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_matrix\Moodle\Infrastructure;

use core\event;
use mod_matrix\Container;
use mod_matrix\Matrix;
use mod_matrix\Moodle;

\defined('MOODLE_INTERNAL') || exit;

final class EventSubscriber
{
    /**
     * @see https://github.com/moodle/moodle/blob/02a2e649e92d570c7fa735bf05f69b588036f761/lib/classes/event/manager.php#L222-L230
     */
    public static function observers(): array
    {
        $map = [
            event\group_created::class => [
                self::class,
                'onGroupCreated',
            ],
            event\group_member_added::class => [
                self::class,
                'onGroupMemberAdded',
            ],
            event\group_member_removed::class => [
                self::class,
                'onGroupMemberRemoved',
            ],
            event\role_assigned::class => [
                self::class,
                'onRoleAssigned',
            ],
            event\role_capabilities_updated::class => [
                self::class,
                'onRoleCapabilitiesUpdated',
            ],
            event\role_deleted::class => [
                self::class,
                'onRoleDeleted',
            ],
            event\role_unassigned::class => [
                self::class,
                'onRoleUnassigned',
            ],
            event\user_enrolment_created::class => [
                self::class,
                'onUserEnrolmentCreated',
            ],
            event\user_enrolment_deleted::class => [
                self::class,
                'onUserEnrolmentDeleted',
            ],
            event\user_enrolment_updated::class => [
                self::class,
                'onUserEnrolmentUpdated',
            ],
        ];

        return \array_map(static function (string $event, array $callback): array {
            return [
                'callback' => $callback,
                'eventname' => $event,
                'internal' => false,
            ];
        }, \array_keys($map), \array_values($map));
    }

    public static function onGroupCreated(event\group_created $event): void
    {
        $courseId = Moodle\Domain\CourseId::fromString($event->courseid);
        $groupId = Moodle\Domain\GroupId::fromString($event->objectid);

        self::prepareRoomsForAllModulesOfCourseAndGroup(
            $courseId,
            $groupId,
        );
    }

    public static function onGroupMemberAdded(event\group_member_added $event): void
    {
        $courseId = Moodle\Domain\CourseId::fromString($event->courseid);
        $groupId = Moodle\Domain\GroupId::fromString($event->objectid);

        self::synchronizeRoomMembersForAllRoomsOfAllModulesInCourseAndGroup(
            $courseId,
            $groupId,
        );
    }

    public static function onGroupMemberRemoved(event\group_member_removed $event): void
    {
        $courseId = Moodle\Domain\CourseId::fromString($event->courseid);
        $groupId = Moodle\Domain\GroupId::fromString($event->objectid);

        self::synchronizeRoomMembersForAllRoomsOfAllModulesInCourseAndGroup(
            $courseId,
            $groupId,
        );
    }

    public static function onRoleAssigned(event\role_assigned $event): void
    {
        self::synchronizeRoomMembersForAllRooms();
    }

    public static function onRoleCapabilitiesUpdated(event\role_capabilities_updated $event): void
    {
        self::synchronizeRoomMembersForAllRooms();
    }

    public static function onRoleDeleted(event\role_deleted $event): void
    {
        self::synchronizeRoomMembersForAllRooms();
    }

    public static function onRoleUnassigned(event\role_unassigned $event): void
    {
        self::synchronizeRoomMembersForAllRooms();
    }

    public static function onUserEnrolmentCreated(event\user_enrolment_created $event): void
    {
        $courseId = Moodle\Domain\CourseId::fromString($event->courseid);

        self::synchronizeRoomMembersForAllRoomsOfAllModulesInCourse($courseId);
    }

    public static function onUserEnrolmentDeleted(event\user_enrolment_deleted $event): void
    {
        $courseId = Moodle\Domain\CourseId::fromString($event->courseid);

        self::synchronizeRoomMembersForAllRoomsOfAllModulesInCourse($courseId);
    }

    public static function onUserEnrolmentUpdated(event\user_enrolment_updated $event): void
    {
        $courseId = Moodle\Domain\CourseId::fromString($event->courseid);

        self::synchronizeRoomMembersForAllRoomsOfAllModulesInCourse($courseId);
    }

    private static function prepareRoomsForAllModulesOfCourseAndGroup(
        Moodle\Domain\CourseId $courseId,
        Moodle\Domain\GroupId $groupId
    ): void {
        global $CFG;

        $container = Container::instance();

        $courseRepository = $container->courseRepository();
        $groupRepository = $container->groupRepository();
        $moodleModuleRepository = $container->moodleModuleRepository();
        $moodleRoomRepository = $container->moodleRoomRepository();
        $userRepository = $container->userRepository();
        $matrixRoomService = $container->matrixRoomService();
        $clock = $container->clock();

        $course = $courseRepository->find($courseId);

        if (!$course instanceof Moodle\Domain\Course) {
            throw new \RuntimeException(\sprintf(
                'Could not find course with id %d.',
                $courseId->toInt(),
            ));
        }

        $group = $groupRepository->find($groupId);

        if (!$group instanceof Moodle\Domain\Group) {
            throw new \RuntimeException(\sprintf(
                'Could not find group with id %d.',
                $groupId->toInt(),
            ));
        }

        $topic = Matrix\Domain\RoomTopic::fromString(\sprintf(
            '%s/course/view.php?id=%d',
            $CFG->wwwroot,
            $courseId->toInt(),
        ));

        $modules = $moodleModuleRepository->findAllBy([
            'course' => $courseId->toInt(),
        ]);

        foreach ($modules as $module) {
            $name = Matrix\Domain\RoomName::fromString(\sprintf(
                '%s: %s (%s)',
                $group->name()->toString(),
                $course->name()->toString(),
                $module->name()->toString(),
            ));

            $room = $moodleRoomRepository->findOneBy([
                'module_id' => $module->id()->toInt(),
                'group_id' => $group->id()->toInt(),
            ]);

            if (!$room instanceof Moodle\Domain\Room) {
                $matrixRoomId = $matrixRoomService->createRoom(
                    $name,
                    $topic,
                    [
                        'org.matrix.moodle.course_id' => $course->id()->toInt(),
                        'org.matrix.moodle.group_id' => $group->id()->toInt(),
                    ],
                );

                $room = Moodle\Domain\Room::create(
                    Moodle\Domain\RoomId::unknown(),
                    $module->id(),
                    $group->id(),
                    $matrixRoomId,
                    Moodle\Domain\Timestamp::fromInt($clock->now()->getTimestamp()),
                    Moodle\Domain\Timestamp::fromInt(0),
                );

                $moodleRoomRepository->save($room);
            }

            $users = $userRepository->findAllUsersEnrolledInCourseAndGroupWithMatrixUserId(
                $course->id(),
                $group->id(),
            );

            $staff = $userRepository->findAllStaffInCourseWithMatrixUserId($course->id());

            $matrixRoomService->synchronizeRoomMembersForRoom(
                $room->matrixRoomId(),
                Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
                    return $user->matrixUserId();
                }, $users)),
                Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
                    return $user->matrixUserId();
                }, $staff)),
            );
        }
    }

    /**
     * @throws \RuntimeException
     */
    private static function synchronizeRoomMembersForAllRooms(): void
    {
        $container = Container::instance();

        $moodleRoomRepository = $container->moodleRoomRepository();
        $moodleModuleRepository = $container->moodleModuleRepository();
        $userRepository = $container->userRepository();
        $matrixRoomService = $container->matrixRoomService();

        $rooms = $moodleRoomRepository->findAll();

        foreach ($rooms as $room) {
            $module = $moodleModuleRepository->findOneBy([
                'id' => $room->moduleId()->toInt(),
            ]);

            if (!$module instanceof Moodle\Domain\Module) {
                throw new \RuntimeException(\sprintf(
                    'Module with id "%d" was not found.',
                    $room->moduleId()->toInt(),
                ));
            }

            $groupId = $room->groupId();

            if (!$groupId instanceof Moodle\Domain\GroupId) {
                $groupId = Moodle\Domain\GroupId::fromInt(0);
            } // Moodle wants zero instead of null

            $users = $userRepository->findAllUsersEnrolledInCourseAndGroupWithMatrixUserId(
                $module->courseId(),
                $groupId,
            );

            $userIdsOfUsers = Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
                return $user->matrixUserId();
            }, $users));

            $staff = $userRepository->findAllStaffInCourseWithMatrixUserId($module->courseId());

            $userIdsOfStaff = Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
                return $user->matrixUserId();
            }, $staff));

            $matrixRoomService->synchronizeRoomMembersForRoom(
                $room->matrixRoomId(),
                $userIdsOfUsers,
                $userIdsOfStaff,
            );
        }
    }

    private static function synchronizeRoomMembersForAllRoomsOfAllModulesInCourse(Moodle\Domain\CourseId $courseId): void
    {
        $container = Container::instance();

        $moodleModuleRepository = $container->moodleModuleRepository();
        $userRepository = $container->userRepository();
        $moodleRoomRepository = $container->moodleRoomRepository();
        $matrixRoomService = $container->matrixRoomService();

        $modules = $moodleModuleRepository->findAllBy([
            'course' => $courseId->toInt(),
        ]);

        $staff = $userRepository->findAllStaffInCourseWithMatrixUserId($courseId);

        $userIdsOfStaff = Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
            return $user->matrixUserId();
        }, $staff));

        foreach ($modules as $module) {
            $rooms = $moodleRoomRepository->findAllBy([
                'module_id' => $module->id()->toInt(),
            ]);

            foreach ($rooms as $room) {
                $groupId = $room->groupId();

                if (!$groupId instanceof Moodle\Domain\GroupId) {
                    $groupId = Moodle\Domain\GroupId::fromInt(0);
                } // Moodle wants zero instead of null

                $users = $userRepository->findAllUsersEnrolledInCourseAndGroupWithMatrixUserId(
                    $courseId,
                    $groupId,
                );

                $userIdsOfUsers = Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
                    return $user->matrixUserId();
                }, $users));

                $matrixRoomService->synchronizeRoomMembersForRoom(
                    $room->matrixRoomId(),
                    $userIdsOfUsers,
                    $userIdsOfStaff,
                );
            }
        }
    }

    private static function synchronizeRoomMembersForAllRoomsOfAllModulesInCourseAndGroup(
        Moodle\Domain\CourseId $courseId,
        Moodle\Domain\GroupId $groupId
    ): void {
        $container = Container::instance();

        $moodleModuleRepository = $container->moodleModuleRepository();
        $userRepository = $container->userRepository();
        $moodleRoomRepository = $container->moodleRoomRepository();
        $matrixRoomService = $container->matrixRoomService();

        $modules = $moodleModuleRepository->findAllBy([
            'course' => $courseId->toInt(),
        ]);

        $users = $userRepository->findAllUsersEnrolledInCourseAndGroupWithMatrixUserId(
            $courseId,
            $groupId,
        );

        $userIdsOfUsers = Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
            return $user->matrixUserId();
        }, $users));

        $staff = $userRepository->findAllStaffInCourseWithMatrixUserId($courseId);

        $userIdsOfStaff = Matrix\Domain\UserIdCollection::fromUserIds(...\array_map(static function (Moodle\Domain\User $user): Matrix\Domain\UserId {
            return $user->matrixUserId();
        }, $staff));

        foreach ($modules as $module) {
            $rooms = $moodleRoomRepository->findAllBy([
                'group_id' => $groupId->toInt(),
                'module_id' => $module->id()->toInt(),
            ]);

            foreach ($rooms as $room) {
                $matrixRoomService->synchronizeRoomMembersForRoom(
                    $room->matrixRoomId(),
                    $userIdsOfUsers,
                    $userIdsOfStaff,
                );
            }
        }
    }
}
