<?php

declare(strict_types=1);

/**
 * @package   mod_matrix
 * @copyright 2020, New Vector Ltd (Trading as Element)
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_matrix\Moodle\Infrastructure;

use mod_matrix\Moodle;

final class ModuleNormalizer
{
    public function denormalize(object $normalized): Moodle\Domain\Module
    {
        return Moodle\Domain\Module::create(
            Moodle\Domain\ModuleId::fromString((string) $normalized->id),
            Moodle\Domain\ModuleType::fromString((string) $normalized->type),
            Moodle\Domain\ModuleName::fromString((string) $normalized->name),
            Moodle\Domain\ModuleTopic::fromString((string) $normalized->topic),
            Moodle\Domain\CourseId::fromString((string) $normalized->course),
            Moodle\Domain\SectionId::fromString((string) $normalized->section),
            Moodle\Domain\Timestamp::fromString((string) $normalized->timecreated),
            Moodle\Domain\Timestamp::fromString((string) $normalized->timemodified),
        );
    }

    public function normalize(Moodle\Domain\Module $denormalized): object
    {
        return (object) [
            'id' => $denormalized->id()->toInt(),
            'type' => $denormalized->type()->toInt(),
            'name' => $denormalized->name()->toString(),
            'topic' => $denormalized->topic()->toString(),
            'course' => $denormalized->courseId()->toInt(),
            'section' => $denormalized->sectionId()->toInt(),
            'timecreated' => $denormalized->timecreated()->toInt(),
            'timemodified' => $denormalized->timemodified()->toInt(),
        ];
    }
}
