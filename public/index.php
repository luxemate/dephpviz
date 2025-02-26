<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use DePhpViz\Web\WebServer;
use DePhpViz\Web\WebServerConfig;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

if (preg_match('/\.(?:css|js|json|png|jpg|jpeg|svg|ico)$/', $_SERVER["REQUEST_URI"])) {
    return false; // serve the requested resource as-is.
}

// Get environment variables or use defaults
$graphDataFile = $_ENV['DEPHPVIZ_GRAPH_DATA'] ?? 'var/graph.json';
$publicDir = $_ENV['DEPHPVIZ_PUBLIC_DIR'] ?? 'public';

// Create logger
$logger = new Logger('webserver');
$logger->pushHandler(new StreamHandler('php://stderr', Level::Info));

// Create server config
$config = new WebServerConfig(
    host: '0.0.0.0', // Listen on all interfaces
    port: 8080,
    publicDir: $publicDir,
    graphDataFile: $graphDataFile
);

// Create and setup the web server
$server = new WebServer($config, $logger);

// Handle the current request
$request = Request::createFromGlobals();
$response = $server->handleRequest($request);
$response->send();
