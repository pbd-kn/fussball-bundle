<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController;

class WettbewerbController extends AbstractAdminController
{
    public const TYPE = 'Wettbewerbe';
    protected const ENTITY = 'wettbewerb';
}
