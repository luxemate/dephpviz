<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Graph;
use DePhpViz\Graph\Model\Node;
use DePhpViz\Parser\Model\AbstractDefinition;
use DePhpViz\Parser\Model\Dependency;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds a dependency graph from parsed definitions and dependencies.
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
     * @param array<array{definition: AbstractDefinition, dependencies: array<Dependency>}> $parsedData
     *
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
            'nodeTypes' => [
                'class' => 0,
                'interface' => 0,
                'trait' => 0
            ],
            'dependencies' => []
        ];

        // First pass: Create nodes for all definitions
        $this->logger->debug('Creating nodes for all definitions...');
        foreach ($parsedData as $item) {
            $definition = $item['definition'];
            $node = Node::fromDefinition($definition);
            $graph->addNode($node);
            $stats['nodeCount']++;

            // Track node types
            $stats['nodeTypes'][$definition->type]++;
        }

        $this->logger->info(sprintf('Created %d nodes (%d classes, %d interfaces, %d traits)',
            $stats['nodeCount'],
            $stats['nodeTypes']['class'],
            $stats['nodeTypes']['interface'],
            $stats['nodeTypes']['trait']
        ));

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
