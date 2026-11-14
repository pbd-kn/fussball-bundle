<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement\VW;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController;

class VWMannschaftController extends AbstractAdminController
{
    public const TYPE = 'Mannschaften';
    protected const ENTITY = 'mannschaft';
}
