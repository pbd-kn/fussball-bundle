<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement\VW;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController;

class VWWettenController extends AbstractAdminController
{
    public const TYPE = 'Wetten';
    protected const ENTITY = 'wette';
}
