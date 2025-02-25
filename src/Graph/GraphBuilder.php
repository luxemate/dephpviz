<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Edge;
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
     * @param LoggerInterface $logger Logger for recording graph building information
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Build a dependency graph from parsed data.
     *
     * @param array<array{class: ClassDefinition, dependencies: array<Dependency>}> $parsedData
     * @return Graph
     */
    public function buildGraph(array $parsedData): Graph
    {
        $this->logger->info('Building dependency graph...');
        $startTime = microtime(true);

        $graph = new Graph();

        // First pass: Create nodes for all classes
        $this->logger->debug('Creating nodes for all classes...');
        foreach ($parsedData as $item) {
            $classDefinition = $item['class'];
            $node = Node::fromClassDefinition($classDefinition);
            $graph->addNode($node);
        }

        $nodeCount = count($graph->getNodes());
        $this->logger->info(sprintf('Created %d nodes', $nodeCount));

        // Second pass: Create edges for all dependencies
        $this->logger->debug('Creating edges for all dependencies...');
        $edgeCount = 0;
        $missingTargets = 0;

        foreach ($parsedData as $item) {
            $dependencies = $item['dependencies'];

            foreach ($dependencies as $dependency) {
                $edge = Edge::fromDependency($dependency);

                // Track missing target nodes
                if (!$graph->hasNode($dependency->targetClass)) {
                    $this->logger->debug(sprintf(
                        'Missing target node for dependency: %s -> %s',
                        $dependency->sourceClass,
                        $dependency->targetClass
                    ));
                    $missingTargets++;
                    continue;
                }

                $graph->addEdge($edge);
                $edgeCount++;
            }
        }

        $this->logger->info(sprintf('Created %d edges', $edgeCount));

        if ($missingTargets > 0) {
            $this->logger->warning(sprintf(
                'Skipped %d dependencies with missing target classes',
                $missingTargets
            ));
        }

        $elapsedTime = microtime(true) - $startTime;
        $this->logger->info(sprintf('Graph built in %.2f seconds', $elapsedTime));

        return $graph;
    }
}
