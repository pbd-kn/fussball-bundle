<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController;

class OrteController extends AbstractAdminController
{
    public const TYPE = 'Orte';
    protected const ENTITY = 'ort';
}
