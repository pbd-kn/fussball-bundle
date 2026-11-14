<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\PageModel;
use Contao\Template;
use PBDKN\FussballBundle\Admin\AdminView;
use PBDKN\FussballBundle\Security\AjaxSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAdminController extends AbstractFussballController
{
    protected const ENTITY = '';
    private AdminView $view;

    public function __construct(DependencyAggregate $dependencies, AdminView $view)
    {
        parent::__construct($dependencies);
        $this->view = $view;
    }

    public function __invoke(Request $request, ContentModel $model, string $section, ?array $classes = null, ?PageModel $pageModel = null): Response
    {
        // Preserve existing tl_content types as inert links, never management UIs.
        return AjaxSecurity::privateResponse(new Response($this->view->backendNotice(static::ENTITY)));
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        return AjaxSecurity::privateResponse(new Response($this->view->backendNotice(static::ENTITY)));
    }
}
