<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Backend;

use PBDKN\FussballBundle\Admin\AdminSchema;

final class BackendModules
{
    public const GROUP = 'pbd_fussball';

    public static function moduleName(string $entity): string
    {
        AdminSchema::get($entity);
        return 'pbd_fussball_'.$entity;
    }

    public static function definitions(): array
    {
        $modules = [];
        foreach (AdminSchema::ENTITIES as $entity => $schema) {
            $modules[self::moduleName($entity)] = ['callback' => FussballBackendModule::class];
        }
        return $modules;
    }

    public static function entity(string $module): string
    {
        foreach (AdminSchema::ENTITIES as $entity => $schema) {
            if (self::moduleName($entity) === $module) {
                return $entity;
            }
        }
        throw new \InvalidArgumentException('Unbekanntes Fußball-Backend-Modul.');
    }
}
