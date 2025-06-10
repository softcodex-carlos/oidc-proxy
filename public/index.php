<?php
file_put_contents(__DIR__ . '/../logs/early.log', 'Index.php started: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

require dirname(__DIR__).'/vendor/autoload.php';
file_put_contents(__DIR__ . '/../logs/early.log', 'Autoload included: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(dirname(__DIR__) . '/.env');

$request = Request::createFromGlobals();
file_put_contents(__DIR__ . '/../logs/early.log', 'Request created: ' . $request->getPathInfo() . PHP_EOL, FILE_APPEND);
$request->setSession(new Session());

$fileLocator = new FileLocator([dirname(__DIR__).'/config']);
$loader = new YamlFileLoader($fileLocator);
$routes = $loader->load('routes.yaml');
file_put_contents(__DIR__ . '/../logs/early.log', 'Routes loaded: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

$context = new RequestContext();
$context->fromRequest($request);

$matcher = new UrlMatcher($routes, $context);

try {
    $parameters = $matcher->match($request->getPathInfo());
    $request->attributes->add($parameters);
    file_put_contents(__DIR__ . '/../logs/debug.log', 'Request matched: ' . $request->getPathInfo() . PHP_EOL, FILE_APPEND);

    $controllerResolver = new ControllerResolver();
    $argumentResolver = new ArgumentResolver();
    $controller = $controllerResolver->getController($request);
    file_put_contents(__DIR__ . '/../logs/debug.log', 'Controller resolved: ' . print_r($controller, true) . PHP_EOL, FILE_APPEND);

    $arguments = $argumentResolver->getArguments($request, $controller);
    $response = call_user_func_array($controller, $arguments);
} catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
    $errorMessage = 'Not Found: ' . $e->getMessage();
    file_put_contents(__DIR__ . '/../logs/error.log', $errorMessage . PHP_EOL, FILE_APPEND);
    $response = new Response($errorMessage, 404);
} catch (\Exception $e) {
    $errorMessage = 'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    file_put_contents(__DIR__ . '/../logs/error.log', $errorMessage . PHP_EOL, FILE_APPEND);
    $response = new Response($errorMessage, 500);
}

$response->send();