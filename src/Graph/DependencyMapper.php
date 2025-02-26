<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Edge;
use DePhpViz\Graph\Model\Graph;
use DePhpViz\Parser\Model\AbstractDefinition;
use DePhpViz\Parser\Model\Dependency;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Maps parsed dependencies to graph edges with validation.
 */
class DependencyMapper
{
    /**
     * @param LoggerInterface $logger Logger for recording mapping information
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Map dependencies to graph edges.
     *
     * @param Graph $graph The graph to add edges to
     * @param array<array{definition: AbstractDefinition, dependencies: array<Dependency>}> $parsedData
     * @return array<string, array{count: int, missing: int, invalid: int, circular: int}>
     *     Statistics on the mapping process
     */
    public function mapDependencies(Graph $graph, array $parsedData): array
    {
        $this->logger->info('Mapping dependencies to graph edges');

        $stats = [
            'use' => ['count' => 0, 'missing' => 0, 'invalid' => 0, 'circular' => 0],
            'extends' => ['count' => 0, 'missing' => 0, 'invalid' => 0, 'circular' => 0],
            'implements' => ['count' => 0, 'missing' => 0, 'invalid' => 0, 'circular' => 0],
            'usesTrait' => ['count' => 0, 'missing' => 0, 'invalid' => 0, 'circular' => 0],
            'total' => ['count' => 0, 'missing' => 0, 'invalid' => 0, 'circular' => 0]
        ];

        // Process each definition and its dependencies
        foreach ($parsedData as $item) {
            $definition = $item['definition'];
            $dependencies = $item['dependencies'];

            $this->logger->debug(sprintf(
                'Processing dependencies for %s (%s, %d dependencies)',
                $definition->fullyQualifiedName,
                $definition->type,
                count($dependencies)
            ));

            foreach ($dependencies as $dependency) {
                $edgeType = $dependency->type;

                // If this is a new edge type, initialize stats
                if (!isset($stats[$edgeType])) {
                    $stats[$edgeType] = ['count' => 0, 'missing' => 0, 'invalid' => 0, 'circular' => 0];
                }

                // Skip if the source node doesn't exist (should never happen)
                if (!$graph->hasNode($dependency->sourceClass)) {
                    $this->logger->warning(sprintf(
                        'Source node %s not found for dependency to %s',
                        $dependency->sourceClass,
                        $dependency->targetClass
                    ));
                    $stats[$edgeType]['invalid']++;
                    $stats['total']['invalid']++;
                    continue;
                }

                // Handle missing target nodes
                if (!$graph->hasNode($dependency->targetClass)) {
                    $this->logger->debug(sprintf(
                        'Target node %s not found for dependency from %s',
                        $dependency->targetClass,
                        $dependency->sourceClass
                    ));
                    $stats[$edgeType]['missing']++;
                    $stats['total']['missing']++;
                    continue;
                }

                // Handle circular dependencies for extends/implements
                if (($edgeType === 'extends' || $edgeType === 'implements') &&
                    $this->wouldCreateCircularDependency($graph, $dependency)) {
                    $this->logger->warning(sprintf(
                        'Circular %s relationship detected: %s -> %s',
                        $edgeType,
                        $dependency->sourceClass,
                        $dependency->targetClass
                    ));
                    $stats[$edgeType]['circular']++;
                    $stats['total']['circular']++;
                    continue;
                }

                // Create and add the edge
                $edge = Edge::fromDependency($dependency);
                $graph->addEdge($edge);

                $stats[$edgeType]['count']++;
                $stats['total']['count']++;
            }
        }

        $this->logger->info(sprintf(
            'Mapped %d dependencies to edges (missing: %d, invalid: %d, circular: %d)',
            $stats['total']['count'],
            $stats['total']['missing'],
            $stats['total']['invalid'],
            $stats['total']['circular']
        ));

        return $stats;
    }

    /**
     * Check if adding this dependency would create a circular inheritance chain.
     *
     * @param Graph $graph The current graph
     * @param Dependency $dependency The dependency to check
     * @return bool True if adding this dependency would create a circular reference
     */
    private function wouldCreateCircularDependency(Graph $graph, Dependency $dependency): bool
    {
        // For 'use' dependencies, we don't need to check for circularity
        if ($dependency->type === 'use') {
            return false;
        }

        // Check if target already depends on source (directly or indirectly)
        $visited = [$dependency->targetClass => true];
        $queue = [$dependency->targetClass];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // Find all outgoing edges from the current node
            foreach ($graph->getEdges() as $edge) {
                if ($edge->source === $current) {
                    // If this edge points back to our source, we have a circular dependency
                    if ($edge->target === $dependency->sourceClass) {
                        return true;
                    }

                    // Only follow extends/implements edges for circularity check
                    if (($edge->type === 'extends' || $edge->type === 'implements') &&
                        !isset($visited[$edge->target])) {
                        $visited[$edge->target] = true;
                        $queue[] = $edge->target;
                    }
                }
            }
        }

        return false;
    }
}
