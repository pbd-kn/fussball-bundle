<?php

declare(strict_types=1);

require __DIR__.'/security.php';

use PBDKN\FussballBundle\Backend\BackendModules;
use PBDKN\FussballBundle\Backend\BackendRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

$renderer = $container->get(BackendRenderer::class);
$bridge = new Symfony\Component\DependencyInjection\ContainerBuilder();
$bridge->set('request_stack', $stack);
$bridge->set(BackendRenderer::class, $renderer);
Contao\System::setContainer($bridge);
$definitions = BackendModules::definitions();
check(count($definitions) === 8, 'Expected eight native backend modules.');
$testTokenChecker->authenticated = true;
foreach ($definitions as $module => $definition) {
    $request = Request::create('/contao?do='.$module);
    $request->attributes->set('_scope', 'backend');
    $request->setSession($session);
    $stack->pop();
    $stack->push($request);
    $callback = new $definition['callback'](null);
    $html = $callback->generate();
    check(str_contains($html, 'data-fussball-admin') && str_contains($html, '<table'), 'Native module failed: '.$module);
    check(str_contains($html, '/contao/fussball/'), 'Module uses incorrect AJAX paths: '.$module);
}
check(in_array('bundles/fussball/css/be_fussball_style.css|static', $GLOBALS['TL_CSS'], true), 'Backend table styles missing.');
foreach (['de', 'en'] as $language) {
    require $root.'/src/Resources/contao/languages/'.$language.'/modules.php';
    foreach ($definitions as $module => $definition) {
        check(!empty($GLOBALS['TL_LANG']['MOD'][$module][0]), 'Missing backend menu label.');
    }
}
foreach (['anonymous', 'frontend'] as $case) {
    $testTokenChecker->authenticated = $case !== 'anonymous';
    $request->attributes->set('_scope', $case === 'frontend' ? 'frontend' : 'backend');
    try {
        $renderer->render($request);
        throw new RuntimeException('Backend rendered for '.$case);
    } catch (AccessDeniedHttpException $e) {
    }
}
$request = Request::create('/contao/fussball/bearbeiteort', 'POST', $placeData + ['REQUEST_TOKEN' => $token]);
$request->attributes->set('_scope', 'frontend');
$request->setSession($session);
check($ajax->bearbeiteort($request)->getStatusCode() === 403, 'AJAX accepted frontend scope.');
echo "OK: all eight native backend callbacks, menu labels, scoped CSS and backend-only access\n";
