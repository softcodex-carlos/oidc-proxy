<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\HttpKernel;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/.env');

$session = new Session(new NativeSessionStorage());
$session->start();

$request = Request::createFromGlobals();
$request->setSession($session);

$routes = (new YamlFileLoader(new FileLocator(dirname(__DIR__) . '/config')))
    ->load('routes.yaml');

$context = new RequestContext();
$context->fromRequest($request);

$matcher = new UrlMatcher($routes, $context);
$resolver = new ControllerResolver();

$kernel = new HttpKernel(new \Symfony\Component\EventDispatcher\EventDispatcher(), $resolver);
$response = $kernel->handle($request);
$response->send();
