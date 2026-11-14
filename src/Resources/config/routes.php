<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // Symfony 5.4 reads PHP attributes through its annotation loader.
    // Symfony 7 only provides the attribute loader.
    $routes->import('../../Controller/Ajax/', class_exists(AttributeDirectoryLoader::class) ? 'attribute' : 'annotation');
};
