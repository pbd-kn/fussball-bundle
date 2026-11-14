<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Backend;

use Contao\CoreBundle\Routing\ScopeMatcher;
use PBDKN\FussballBundle\Admin\AdminView;
use PBDKN\FussballBundle\Security\AjaxSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BackendRenderer
{
    private AdminView $view;
    private AjaxSecurity $security;
    private ScopeMatcher $scopeMatcher;

    public function __construct(AdminView $view, AjaxSecurity $security, ScopeMatcher $scopeMatcher)
    {
        $this->view = $view;
        $this->security = $security;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function render(Request $request): string
    {
        if (!$this->scopeMatcher->isBackendRequest($request)) {
            throw new AccessDeniedHttpException('Die Fußball-Verwaltung ist nur im Backend verfügbar.');
        }
        $this->security->assertAdministrator();
        $query = $request->query->all();
        $module = $query['do'] ?? '';
        if (!is_string($module)) {
            throw new \InvalidArgumentException('Ungültiges Backend-Modul.');
        }
        $entity = BackendModules::entity($module);
        $GLOBALS['TL_CSS'][] = 'bundles/fussball/css/be_fussball_style.css|static';

        return '<div id="tl_fussball" class="tl_listing_container">'.$this->view->render($entity, $request).'</div>';
    }
}
