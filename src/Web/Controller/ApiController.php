<?php

declare(strict_types=1);

namespace DePhpViz\Web\Controller;

use DePhpViz\Web\Exception\WebServerException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for API endpoints.
 */
class ApiController
{
    /**
     * @param string $graphDataFile Path to the graph data file
     * @param LoggerInterface $logger Logger for API operations
     */
    public function __construct(
        private readonly string $graphDataFile,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Handle requests to the graph data API endpoint.
     */
    public function getGraphData(Request $request): Response
    {
        $this->logger->info('Serving graph data', [
            'client' => $request->getClientIp(),
            'file' => $this->graphDataFile
        ]);

        if (!file_exists($this->graphDataFile)) {
            $this->logger->error('Graph data file not found', [
                'file' => $this->graphDataFile
            ]);

            return new JsonResponse([
                'error' => 'Graph data not available',
                'message' => 'The graph data file could not be found'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = file_get_contents($this->graphDataFile);

            if ($data === false) {
                throw new WebServerException('Failed to read graph data file');
            }

            $jsonData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($jsonData)) {
                throw new WebServerException('Invalid JSON in graph data file');
            }

            // Transform data structure if needed
            if (isset($jsonData['nodes']) && is_array($jsonData['nodes'])) {
                // If nodes is an associative array, convert it to a sequential array
                $jsonData['nodes'] = array_values($jsonData['nodes']);
            }

            if (isset($jsonData['edges']) && is_array($jsonData['edges'])) {
                // If edges is an associative array, convert it to a sequential array
                $jsonData['edges'] = array_values($jsonData['edges']);
            }

            $response = new JsonResponse($jsonData);
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET');
            $response->headers->set('Cache-Control', 'no-store, max-age=0');

            return $response;
        } catch (\JsonException $e) {
            $this->logger->error('Invalid JSON in graph data file', [
                'file' => $this->graphDataFile,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Invalid graph data',
                'message' => 'The graph data file contains invalid JSON'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $this->logger->error('Failed to serve graph data', [
                'file' => $this->graphDataFile,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Server error',
                'message' => 'An error occurred while serving the graph data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle requests to check server status.
     */
    public function getStatus(Request $request): Response
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => time(),
            'graphDataAvailable' => file_exists($this->graphDataFile)
        ]);
    }
}
