<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Graph;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Serializes a graph to various formats.
 */
class GraphSerializer
{
    /**
     * @param Filesystem $filesystem Symfony Filesystem component
     * @param LoggerInterface $logger Logger for recording serialization information
     */
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Serialize a graph to JSON and save to a file.
     *
     * @param Graph $graph The graph to serialize
     * @param string $outputFile The output file path
     * @return bool True if successful
     */
    public function serializeToJson(Graph $graph, string $outputFile): bool
    {
        $this->logger->info(sprintf('Serializing graph to JSON: %s', $outputFile));
        $startTime = microtime(true);

        try {
            // Ensure the directory exists
            $directory = dirname($outputFile);
            if (!$this->filesystem->exists($directory)) {
                $this->filesystem->mkdir($directory, 0755);
            }

            // Convert graph to JSON
            $json = $graph->toJson(JSON_PRETTY_PRINT);

            // Write to file
            $this->filesystem->dumpFile($outputFile, $json);

            $fileSize = filesize($outputFile);
            $elapsedTime = microtime(true) - $startTime;

            $this->logger->info(sprintf(
                'Graph serialized successfully in %.2f seconds (%.2f MB)',
                $elapsedTime,
                $fileSize / 1024 / 1024
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Failed to serialize graph: %s',
                $e->getMessage()
            ));

            return false;
        }
    }
}
