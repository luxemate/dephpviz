<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();
$routes->add('home', new Route('/', [
    '_controller' => function () {
        $content = file_get_contents(__DIR__ . '/templates/index.html');
        return new Response($content);
    }
]));

$routes->add('api_graph', new Route('/api/graph', [
    '_controller' => function () {
        $graphData = file_get_contents(dirname(__DIR__) . '/var/graph.json');
        return new Response($graphData, 200, ['Content-Type' => 'application/json']);
    }
]));

$request = Request::createFromGlobals();
$context = new RequestContext();
$context->fromRequest($request);

$matcher = new UrlMatcher($routes, $context);
try {
    $parameters = $matcher->match($request->getPathInfo());
    $response = $parameters['_controller']();
} catch (\Exception $e) {
    $response = new Response('Not found', 404);
}

$response->send();
