<?php

declare(strict_types=1);

namespace DePhpViz\Web\Controller;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for handling requests to static files and the main index.
 */
class IndexController
{
    /**
     * @param string $publicDir The public directory for static files
     * @param LoggerInterface $logger Logger for index operations
     */
    public function __construct(
        private readonly string $publicDir,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Handle request to the root index.
     */
    public function index(Request $request): Response
    {
        $this->logger->info('Serving index', [
            'client' => $request->getClientIp()
        ]);

        $indexFile = $this->publicDir . '/index.html';

        if (!file_exists($indexFile)) {
            $this->logger->error('Index file not found', [
                'file' => $indexFile
            ]);

            return new Response(
                '<html><body><h1>Error</h1><p>Index file not found</p></body></html>',
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'text/html']
            );
        }

        return new BinaryFileResponse($indexFile);
    }

    /**
     * Handle requests to static assets.
     */
    public function staticAsset(Request $request, string $path): Response
    {
        $filePath = $this->publicDir . '/' . $path;

        if (!file_exists($filePath) || is_dir($filePath)) {
            $this->logger->warning('Static asset not found', [
                'path' => $path,
                'filePath' => $filePath
            ]);

            return new Response('Not Found', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);

        // Set MIME type based on file extension
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
        ];

        if (isset($mimeTypes[$extension])) {
            $response->headers->set('Content-Type', $mimeTypes[$extension]);
        }

        // Set cache control headers for static assets
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }
}
