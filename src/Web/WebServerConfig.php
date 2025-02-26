<?php

declare(strict_types=1);

namespace DePhpViz\Web;

/**
 * Configuration for the web server.
 */
class WebServerConfig
{
    /**
     * @param string $host The server host
     * @param int $port The server port
     * @param string $publicDir The public directory for static files
     * @param string $graphDataFile Path to the graph data file
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8080,
        private readonly string $publicDir = 'public',
        private readonly string $graphDataFile = 'var/graph.json'
    ) {
    }

    /**
     * Get the server host.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get the server port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get the public directory path.
     */
    public function getPublicDir(): string
    {
        return $this->publicDir;
    }

    /**
     * Get the graph data file path.
     */
    public function getGraphDataFile(): string
    {
        return $this->graphDataFile;
    }

    /**
     * Get the server URL.
     */
    public function getServerUrl(): string
    {
        return sprintf('http://%s:%d', $this->host, $this->port);
    }

    /**
     * Get the visualization URL.
     */
    public function getVisualizationUrl(): string
    {
        return $this->getServerUrl() . '/';
    }
}
