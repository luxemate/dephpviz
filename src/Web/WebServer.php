<?php

declare(strict_types=1);

namespace DePhpViz\Web;

use DePhpViz\Web\Controller\ApiController;
use DePhpViz\Web\Controller\IndexController;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Web server for serving the dependency visualization.
 */
class WebServer
{
    private RouteCollection $routes;

    /**
     * @param WebServerConfig $config Server configuration
     * @param LoggerInterface $logger Logger for server operations
     */
    public function __construct(
        private readonly WebServerConfig $config,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        $this->setupRoutes();
    }

    /**
     * Start the web server.
     *
     * @param bool $background Whether to run in background
     * @return Process|null The server process if running in background
     */
    public function start(bool $background = true): ?Process
    {
        $host = $this->config->getHost();
        $port = $this->config->getPort();
        $publicDir = $this->config->getPublicDir();

        $this->logger->info('Starting web server', [
            'host' => $host,
            'port' => $port,
            'publicDir' => $publicDir,
            'url' => $this->config->getServerUrl()
        ]);

        // Create the PHP built-in server command
        $command = [
            PHP_BINARY,
            '-S', "{$host}:{$port}",
            '-t', $publicDir,
            '-d', "variables_order=EGPCS", // This is important
            __DIR__ . '/../../public/index.php'
        ];

        $process = new Process(
            $command,
            null,
            [
                'DEPHPVIZ_GRAPH_DATA' => $this->config->getGraphDataFile(),
                'DEPHPVIZ_PUBLIC_DIR' => $publicDir
            ]
        );

        if ($background) {
            $process->start();

            $this->logger->info('Web server started in background', [
                'pid' => $process->getPid()
            ]);

            return $process;
        } else {
            $this->logger->info('Web server running in foreground');
            $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    $this->logger->error($buffer);
                } else {
                    $this->logger->info($buffer);
                }
            });

            return null;
        }
    }

    /**
     * Handle an HTTP request.
     */
    public function handleRequest(Request $request): Response
    {
        $context = new RequestContext();
        $context->fromRequest($request);

        $matcher = new UrlMatcher($this->routes, $context);

        try {
            $parameters = $matcher->match($request->getPathInfo());
            $controller = $parameters['_controller'];
            unset($parameters['_controller'], $parameters['_route']);

            return $controller($request, ...\array_values($parameters));
        } catch (ResourceNotFoundException $e) {
            $this->logger->warning('Route not found', [
                'path' => $request->getPathInfo()
            ]);

            return new Response('Not Found', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Error handling request', [
                'path' => $request->getPathInfo(),
                'error' => $e->getMessage()
            ]);

            return new Response('Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the browser launch URL.
     */
    public function getBrowserUrl(): string
    {
        return $this->config->getVisualizationUrl();
    }

    /**
     * Set up the routes.
     */
    private function setupRoutes(): void
    {
        $this->routes = new RouteCollection();

        // Create controllers
        $indexController = new IndexController(
            $this->config->getPublicDir(),
            $this->logger
        );

        $apiController = new ApiController(
            $this->config->getGraphDataFile(),
            $this->logger
        );

        // Define routes
        $this->routes->add('index', new Route('/', [
            '_controller' => [$indexController, 'index']
        ], [], [], '', [], ['GET']));

        $this->routes->add('api_graph', new Route('/api/graph', [
            '_controller' => [$apiController, 'getGraphData']
        ], [], [], '', [], ['GET']));

        $this->routes->add('api_status', new Route('/api/status', [
            '_controller' => [$apiController, 'getStatus']
        ], [], [], '', [], ['GET']));
    }
}
