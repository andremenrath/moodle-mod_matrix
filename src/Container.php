<?php

declare(strict_types=1);

/**
 * @package   mod_matrix
 * @copyright 2020, New Vector Ltd (Trading as Element)
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_matrix;

use Ergebnis\Clock;

final class Container
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var array<string, \Closure>
     */
    private $definitions = [];

    /**
     * @var array<string, object>
     */
    private $services = [];

    private function __construct()
    {
        $this->define(Clock\Clock::class, static function (): Clock\Clock {
            $timezone = new \DateTimeZone(date_default_timezone_get());

            return new Clock\SystemClock($timezone);
        });

        $this->define(Moodle\Application\Api::class, static function (self $container): Moodle\Application\Api {
            $configuration = $container->configuration();

            return new Moodle\Infrastructure\CurlBasedApi(
                $configuration->hsUrl(),
                $configuration->accessToken()
            );
        });

        $this->define(Moodle\Application\Configuration::class, static function (): Moodle\Application\Configuration {
            $object = get_config('mod_matrix');

            return Moodle\Application\Configuration::fromObject($object);
        });

        $this->define(Moodle\Application\ModuleRepository::class, static function (self $container): Moodle\Application\ModuleRepository {
            return new Moodle\Infrastructure\DatabaseBasedModuleRepository(
                $container->database(),
                new Moodle\Infrastructure\ModuleNormalizer()
            );
        });

        $this->define(Moodle\Application\RoomRepository::class, static function (self $container): Moodle\Application\RoomRepository {
            return new Moodle\Infrastructure\DatabaseBasedRoomRepository($container->database());
        });

        $this->define(Moodle\Application\Service::class, static function (self $container): Moodle\Application\Service {
            return new Moodle\Application\Service(
                $container->api(),
                $container->configuration(),
                $container->roomRepository(),
                $container->clock()
            );
        });

        $this->define(\moodle_database::class, static function (): \moodle_database {
            global $DB;

            if (!$DB instanceof \moodle_database) {
                throw new \RuntimeException(sprintf(
                    'Expected global variable $DB to reference an instance of "%s", got "%s" instead.',
                    \moodle_database::class,
                    is_object($DB) ? get_class($DB) : gettype($DB)
                ));
            }

            return $DB;
        });
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function api(): Moodle\Application\Api
    {
        return $this->resolve(Moodle\Application\Api::class);
    }

    public function configuration(): Moodle\Application\Configuration
    {
        return $this->resolve(Moodle\Application\Configuration::class);
    }

    public function clock(): Clock\Clock
    {
        return $this->resolve(Clock\Clock::class);
    }

    public function database(): \moodle_database
    {
        return $this->resolve(\moodle_database::class);
    }

    public function moduleRepository(): Moodle\Application\ModuleRepository
    {
        return $this->resolve(Moodle\Application\ModuleRepository::class);
    }

    public function roomRepository(): Moodle\Application\RoomRepository
    {
        return $this->resolve(Moodle\Application\RoomRepository::class);
    }

    public function service(): Moodle\Application\Service
    {
        return $this->resolve(Moodle\Application\Service::class);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function define(string $identifier, \Closure $closure): void
    {
        if (array_key_exists($identifier, $this->definitions)) {
            throw new \InvalidArgumentException(sprintf(
                'A service definition for identifier "%s" has already been registered.',
                $identifier
            ));
        }

        $container = $this;

        $this->definitions[$identifier] = static function () use ($closure, $container) {
            return $closure($container);
        };
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function resolve(string $identifier)
    {
        if (!array_key_exists($identifier, $this->definitions)) {
            throw new \InvalidArgumentException(sprintf(
                'A service definition for identifier "%s" has not been registered.',
                $identifier
            ));
        }

        if (!array_key_exists($identifier, $this->services)) {
            $definition = $this->definitions[$identifier];

            $service = $definition($this);

            $this->services[$identifier] = $service;
        }

        return $this->services[$identifier];
    }
}
