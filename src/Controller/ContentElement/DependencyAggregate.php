<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Doctrine\DBAL\Connection;
use PBDKN\FussballBundle\Util\CgiUtil;
use PBDKN\FussballBundle\Util\FussballUtil;

final class DependencyAggregate
{
    public Connection $connection;
    public ScopeMatcher $scopeMatcher;
    public CgiUtil $cgiUtil;
    public FussballUtil $fussballUtil;

    public function __construct(Connection $connection, ScopeMatcher $scopeMatcher, CgiUtil $cgiUtil, FussballUtil $fussballUtil)
    {
        $this->connection = $connection;
        $this->scopeMatcher = $scopeMatcher;
        $this->cgiUtil = $cgiUtil;
        $this->fussballUtil = $fussballUtil;
    }
}
