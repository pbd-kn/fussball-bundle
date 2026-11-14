<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement\VW;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController;

class VWTeilnehmerController extends AbstractAdminController
{
    public const TYPE = 'Teilnehmer';
    protected const ENTITY = 'teilnehmer';
}
