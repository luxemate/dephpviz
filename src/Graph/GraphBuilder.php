<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Graph;
use DePhpViz\Graph\Model\Node;
use DePhpViz\Parser\Model\ClassDefinition;
use DePhpViz\Parser\Model\Dependency;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds a dependency graph from parsed class definitions and dependencies.
 */
class GraphBuilder
{
    /**
     * @param DependencyMapper $dependencyMapper The dependency mapper service
     * @param LoggerInterface $logger Logger for recording graph building information
     */
    public function __construct(
        private readonly DependencyMapper $dependencyMapper,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Build a dependency graph from parsed data.
     *
     * @param array<array{class: ClassDefinition, dependencies: array<Dependency>}> $parsedData
     * @return array{
     *     graph: Graph,
     *     stats: array{
     *         nodeCount: int,
     *         edgeCount: int,
     *         buildTime: float,
     *         dependencies: array<string, array{count: int, missing: int, invalid: int, circular: int}>,
     *     }
     * } The graph and build statistics
     */
    public function buildGraph(array $parsedData): array
    {
        $this->logger->info('Building dependency graph...');
        $startTime = microtime(true);

        $graph = new Graph();
        $stats = [
            'nodeCount' => 0,
            'edgeCount' => 0,
            'buildTime' => 0,
            'dependencies' => []
        ];

        // First pass: Create nodes for all classes
        $this->logger->debug('Creating nodes for all classes...');
        foreach ($parsedData as $item) {
            $classDefinition = $item['class'];
            $node = Node::fromClassDefinition($classDefinition);
            $graph->addNode($node);
            $stats['nodeCount']++;
        }

        $this->logger->info(sprintf('Created %d nodes', $stats['nodeCount']));

        // Second pass: Create edges for all dependencies using the mapper
        $this->logger->debug('Mapping dependencies to edges...');
        $dependencyStats = $this->dependencyMapper->mapDependencies($graph, $parsedData);

        $stats['edgeCount'] = $dependencyStats['total']['count'];
        $stats['dependencies'] = $dependencyStats;

        $elapsedTime = microtime(true) - $startTime;
        $stats['buildTime'] = $elapsedTime;

        $this->logger->info(sprintf('Graph built in %.2f seconds', $elapsedTime));

        return [
            'graph' => $graph,
            'stats' => $stats
        ];
    }
}
