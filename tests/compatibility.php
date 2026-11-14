<?php

declare(strict_types=1);

// Run against the vendor/autoload.php of either a Contao 4.13 or 5.7 installation.
$autoload = $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Usage: php tests/compatibility.php /path/to/vendor/autoload.php\n");
    exit(1);
}

$loader = require $autoload;
$root = dirname(__DIR__);
// Always test this checkout, even if another version is installed in that application.
$loader->addPsr4('PBDKN\\FussballBundle\\', $root.'/src/', true);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src')) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && preg_match('/^namespace ([^;]+);/m', file_get_contents($file->getPathname()), $match)) {
        $loader->addClassMap([$match[1].'\\'.$file->getBasename('.php') => $file->getPathname()]);
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$framework = (new ReflectionClass(Contao\CoreBundle\Framework\ContaoFramework::class))->newInstanceWithoutConstructor();
$matcher = static function (string $scope): Symfony\Component\HttpFoundation\RequestMatcherInterface {
    return new class($scope) implements Symfony\Component\HttpFoundation\RequestMatcherInterface {
        private string $scope;

        public function __construct(string $scope)
        {
            $this->scope = $scope;
        }

        public function matches(Symfony\Component\HttpFoundation\Request $request): bool
        {
            return $request->attributes->get('_scope') === $this->scope;
        }
    };
};
$stack = new Symfony\Component\HttpFoundation\RequestStack();
$scopeClass = new ReflectionClass(Contao\CoreBundle\Routing\ScopeMatcher::class);
$scopeArguments = [$matcher('backend'), $matcher('frontend')];
if ($scopeClass->getConstructor()->getNumberOfParameters() > 2) {
    $scopeArguments[] = $stack;
}
$scopeMatcher = $scopeClass->newInstanceArgs($scopeArguments);
$testTokenChecker = new class extends Contao\CoreBundle\Security\Authentication\Token\TokenChecker {
    public bool $authenticated = true;

    public function __construct()
    {
    }

    public function hasBackendUser(): bool
    {
        return $this->authenticated;
    }

    public function getBackendUsername(): ?string
    {
        return $this->authenticated ? 'compat-admin' : null;
    }
};
$csrfTokens = new Symfony\Component\Security\Csrf\CsrfTokenManager(null, new Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage($stack));
$generatedRoutes = new Symfony\Component\Routing\RouteCollection();
$generatedRoutes->add('contao_backend_login', new Symfony\Component\Routing\Route('/contao/login'));
$generatedRoutes->add('contao_backend', new Symfony\Component\Routing\Route('/contao'));
$urlGenerator = new Symfony\Component\Routing\Generator\UrlGenerator($generatedRoutes, new Symfony\Component\Routing\RequestContext());
$twigLoader = new Twig\Loader\FilesystemLoader();
$twigLoader->addPath($root.'/src/Resources/views', 'Fussball');
$twig = new Twig\Environment($twigLoader);

$container = new Symfony\Component\DependencyInjection\ContainerBuilder();
$container->setParameter('contao.csrf_token_name', 'fussball-test');
$dependencies = [
    Doctrine\DBAL\Connection::class => $connection,
    Contao\CoreBundle\Framework\ContaoFramework::class => $framework,
    Contao\CoreBundle\Routing\ScopeMatcher::class => $scopeMatcher,
    Twig\Environment::class => $twig,
    Contao\CoreBundle\Security\Authentication\Token\TokenChecker::class => $testTokenChecker,
    'contao.csrf.token_manager' => $csrfTokens,
    Symfony\Component\Routing\Generator\UrlGeneratorInterface::class => $urlGenerator,
    Psr\Log\LoggerInterface::class => new Psr\Log\NullLogger(),
];
foreach ($dependencies as $id => $service) {
    $container->register($id)->setSynthetic(true)->setPublic(true);
}
$container->setAlias(Symfony\Component\DependencyInjection\ContainerInterface::class, 'service_container');
$container->setAlias(Psr\Container\ContainerInterface::class, 'service_container');
$yamlLoader = new Symfony\Component\DependencyInjection\Loader\YamlFileLoader($container, new Symfony\Component\Config\FileLocator());
$yamlLoader->load($root.'/src/Resources/config/services.yaml');
$elements = $container->findTaggedServiceIds('contao.content_element');
check(count($elements) === 15, 'Expected all 15 content elements to be registered.');
$container->compile();
foreach ($dependencies as $id => $service) {
    $container->set($id, $service);
}

// Construction must work without an existing database or an initialized Contao framework.
foreach ($elements as $class => $tags) {
    check(count($tags) === 1, 'Duplicate content element registration: '.$class);
    check($tags[0]['type'] === $class::TYPE, 'Persisted content element type changed: '.$class);
    $container->get($class);
}
$container->get(PBDKN\FussballBundle\Controller\Ajax\FussballRequestClass::class);
check(!$connection->isConnected(), 'Controller construction unexpectedly opened the database.');
echo "OK: container, 15 element types, constructors without database access\n";

// Use an isolated database for rendering previews. Never connect to the application's DB.
$connection->executeStatement('CREATE TABLE tl_hy_config (id INTEGER PRIMARY KEY, Name TEXT, aktuell INTEGER, value1 TEXT, value2 TEXT, value3 TEXT, value4 TEXT, value5 TEXT)');
$connection->executeStatement("INSERT INTO tl_hy_config VALUES (1, 'Wettbewerb', 1, 'WM2026', '12', 'E', '2026-06-11', '2026-07-19')");
$connection->executeStatement('CREATE TABLE tl_user (username TEXT, admin TEXT, disable TEXT, start INTEGER, stop INTEGER)');
$connection->executeStatement("INSERT INTO tl_user VALUES ('compat-admin', '1', '', 0, 0)");
$request = Symfony\Component\HttpFoundation\Request::create('/contao');
$request->attributes->set('_scope', 'backend');
$model = (new ReflectionClass(Contao\ContentModel::class))->newInstanceWithoutConstructor();
foreach ($elements as $class => $tags) {
    $response = $container->get($class)($request, $model, 'main');
    $expected = is_subclass_of($class, PBDKN\FussballBundle\Controller\ContentElement\AbstractAdminController::class) ? 'Fußball-Verwaltung im Backend' : 'WM2026';
    check(str_contains($response->getContent(), $expected), 'Backend preview failed: '.$class);
}
echo "OK: all 15 backend previews with SQLite fixture\n";

// Exercise the actual Symfony loader selected by the shared routing configuration.
$locator = new Symfony\Component\Config\FileLocator();
if (class_exists(Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader::class)) {
    $classLoader = new Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader();
    $directoryLoader = new Symfony\Component\Routing\Loader\AttributeDirectoryLoader($locator, $classLoader);
    $fileLoader = new Symfony\Component\Routing\Loader\AttributeFileLoader($locator, $classLoader);
} else {
    $classLoader = new Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader();
    $directoryLoader = new Symfony\Component\Routing\Loader\AnnotationDirectoryLoader($locator, $classLoader);
    $fileLoader = new Symfony\Component\Routing\Loader\AnnotationFileLoader($locator, $classLoader);
}
$phpLoader = new Symfony\Component\Routing\Loader\PhpFileLoader($locator);
new Symfony\Component\Config\Loader\LoaderResolver([$classLoader, $directoryLoader, $fileLoader, $phpLoader]);
$routes = $phpLoader->load($root.'/src/Resources/config/routes.php');
foreach ($routes as $route) {
    $route->setPath(str_replace('%contao.backend.route_prefix%', '/contao', $route->getPath()));
}
$generatedRoutes->addCollection($routes);
$ajaxClass = new ReflectionClass(PBDKN\FussballBundle\Controller\Ajax\FussballRequestClass::class);
$expectedRoutes = 0;
foreach ($ajaxClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    foreach ($method->getAttributes(Symfony\Component\Routing\Annotation\Route::class) as $attribute) {
        ++$expectedRoutes;
        $arguments = $attribute->getArguments();
        $route = $routes->get($arguments['name']);
        check($route !== null, 'AJAX route missing: '.$arguments['name']);
        check($route->getDefault('_scope') === 'backend', 'AJAX route must use the backend scope.');
        check(str_starts_with($route->getPath(), '/contao/fussball/'), 'AJAX route outside backend prefix.');
        check($route->getDefault('_controller') === $ajaxClass->getName().'::'.$method->getName(), 'Incorrect route controller.');
    }
}
check($expectedRoutes > 0 && count($routes) === $expectedRoutes, 'Unexpected route count.');
echo 'OK: '.$expectedRoutes." AJAX routes loaded with Symfony ".Symfony\Component\HttpKernel\Kernel::VERSION."\n";

foreach (glob($root.'/src/Resources/contao/dca/tl_hy_*.php') as $file) {
    require $file;
    $table = basename($file, '.php');
    $dca = $GLOBALS['TL_DCA'][$table];
    check($dca['config']['dataContainer'] === Contao\DC_Table::class, 'Invalid data container: '.$table);
    foreach (array_merge($dca['list']['sorting']['fields'], $dca['list']['label']['fields']) as $field) {
        check(isset($dca['fields'][$field]), 'Undefined list field '.$table.'.'.$field);
    }
}
require $root.'/src/Resources/contao/dca/tl_content.php';
foreach ($elements as $tags) {
    check(isset($GLOBALS['TL_DCA']['tl_content']['palettes'][$tags[0]['type']]), 'Element palette missing.');
}
echo "OK: nine table DCA definitions and 15 palettes\n";

// Scope detection must also work when TL_MODE is not defined (Contao 5).
$scopeContainer = new Symfony\Component\DependencyInjection\ContainerBuilder();
$scopeContainer->set('request_stack', $stack);
$scopeContainer->set('contao.routing.scope_matcher', $scopeMatcher);
Contao\System::setContainer($scopeContainer);
$GLOBALS['TL_CSS'] = [];
require $root.'/src/Resources/contao/config/config.php';
check($GLOBALS['TL_CSS'] === [], 'CSS added without a request.');
$stack->push($request);
require $root.'/src/Resources/contao/config/config.php';
check($GLOBALS['TL_CSS'] === [], 'Frontend CSS added in the backend.');
$request->attributes->set('_scope', 'frontend');
$styles = new PBDKN\FussballBundle\EventListener\FrontendStyles();
check(in_array([get_class($styles), 'onGeneratePage'], $GLOBALS['TL_HOOKS']['generatePage'], true), 'Frontend stylesheet hook missing.');
$styles->onGeneratePage();
$styles->onGeneratePage();
check(count($GLOBALS['TL_CSS']) === 1, 'Frontend CSS missing.');
echo "OK: frontend CSS registered after routing, once per page, without TL_MODE\n";

// Render the overview twice to catch global PHP function redeclarations.
$view = new class {
    public array $Gruppen = [];
    public array $Spiele = [];

    public function render(string $file): string
    {
        ob_start();
        include $file;
        return ob_get_clean();
    }
};
$file = $root.'/src/Resources/contao/templates/content_element/ce_fe_uebersicht.html5';
check($view->render($file) === $view->render($file), 'Repeated overview rendering differs.');
echo "OK: overview template can be rendered twice\n";

// Exercise shared scoring without changing any stored points.
$controller = $container->get(PBDKN\FussballBundle\Controller\ContentElement\FE\FePunkteController::class);
$scoring = new ReflectionMethod($controller, 'berechneWettPunkte');
$scoring->setAccessible(true);
check($scoring->invoke($controller, 's', 2, 1, 0, 2, 1, 5, 2) === 5, 'Exact score changed.');
check($scoring->invoke($controller, 's', 3, 1, 0, 2, 1, 5, 2) === 2, 'Trend score changed.');
check($scoring->invoke($controller, 's', -1, -1, 0, 2, 1, 5, 2) === 0, 'Missing tip must score zero.');
check($scoring->invoke($controller, 'p', 7, 0, 7, 0, 0, 5, 2) === 5, 'Team tip score changed.');
echo "OK: scoring regression examples\n";
