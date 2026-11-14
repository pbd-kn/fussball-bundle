<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Backend;

use Contao\DataContainer;
use Contao\System;

// Contao instantiates BE_MOD callbacks itself. Resolve the application service
// only when generate() is called inside the authenticated backend shell.
final class FussballBackendModule
{
    public function __construct(?DataContainer $dc = null)
    {
    }

    public function generate(): string
    {
        $container = System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();
        if ($request === null) {
            throw new \LogicException('Die Backend-Verwaltung benötigt eine HTTP-Anfrage.');
        }
        return $container->get(BackendRenderer::class)->render($request);
    }
}
