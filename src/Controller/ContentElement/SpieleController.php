<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController;

class SpieleController extends AbstractAdminController
{
    public const TYPE = 'Spiele';
    protected const ENTITY = 'spiel';
}
