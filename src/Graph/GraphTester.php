<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Graph;
use DePhpViz\Parser\Model\ClassDefinition;
use DePhpViz\Parser\Model\Dependency;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tests graph creation with a subset of parsed data.
 */
class GraphTester
{
    /**
     * @param GraphBuilder $graphBuilder The graph builder service
     * @param GraphSerializer $graphSerializer The graph serializer service
     */
    public function __construct(
        private readonly GraphBuilder $graphBuilder,
        private readonly GraphSerializer $graphSerializer
    ) {
    }

    /**
     * Generate a test graph using a limited subset of the parsed data.
     *
     * @param array<array{class: ClassDefinition, dependencies: array<Dependency>}> $parsedData
     * @param int $maxNodes Maximum number of nodes to include in the test graph
     * @param string $outputFile Path to save the test graph JSON
     * @param SymfonyStyle|null $io Console IO for output (optional)
     * @return Graph The generated test graph
     */
    public function generateTestGraph(
        array $parsedData,
        int $maxNodes,
        string $outputFile,
        ?SymfonyStyle $io = null
    ): Graph {
        if ($io) {
            $io->section('Generating test graph with subset of data');
            $io->text(sprintf('Using up to %d nodes from %d available classes', $maxNodes, count($parsedData)));
        }

        // Take a subset of the data
        $subset = $this->selectMostConnectedSubset($parsedData, $maxNodes);

        if ($io) {
            $io->text(sprintf('Selected %d classes for the test graph', count($subset)));
        }

        // Build graph with subset
        $graph = $this->graphBuilder->buildGraph($subset);

        // Output some statistics
        $nodeCount = count($graph->getNodes());
        $edgeCount = count($graph->getEdges());

        if ($io) {
            $io->text([
                sprintf('Generated test graph with %d nodes and %d edges', $nodeCount, $edgeCount),
                sprintf('Connectivity ratio: %.2f edges per node', $nodeCount > 0 ? $edgeCount / $nodeCount : 0)
            ]);
        }

        // Serialize to JSON
        $this->graphSerializer->serializeToJson($graph, $outputFile);

        if ($io) {
            $io->success(sprintf('Test graph saved to %s', $outputFile));
        }

        return $graph;
    }

    /**
     * Validate the generated graph for correctness.
     *
     * @param Graph $graph The graph to validate
     * @param SymfonyStyle|null $io Console IO for output (optional)
     * @return bool True if the graph is valid
     */
    public function validateGraph(Graph $graph, ?SymfonyStyle $io = null): bool
    {
        $valid = true;
        $issues = [];

        // Check for orphaned nodes (no connections)
        $orphanedNodes = $this->findOrphanedNodes($graph);
        if (count($orphanedNodes) > 0) {
            $valid = false;
            $issues[] = sprintf('Found %d orphaned nodes (no connections)', count($orphanedNodes));

            if ($io && $io->isVerbose()) {
                $io->text('Sample of orphaned nodes:');
                $io->listing(array_slice($orphanedNodes, 0, min(5, count($orphanedNodes))));
            }
        }

        // Check for disconnected subgraphs
        $subgraphs = $this->identifySubgraphs($graph);
        if (count($subgraphs) > 1) {
            $issues[] = sprintf('Graph contains %d disconnected subgraphs', count($subgraphs));

            if ($io && $io->isVerbose()) {
                $io->text('Subgraph sizes:');
                foreach ($subgraphs as $index => $subgraph) {
                    $io->text(sprintf('  Subgraph %d: %d nodes', $index + 1, count($subgraph)));
                }
            }
        }

        // Report validation results
        if ($io) {
            if ($valid && count($issues) === 0) {
                $io->success('Graph validation passed with no issues');
            } else {
                $io->note('Graph validation completed with observations:');
                $io->listing($issues);
            }
        }

        return $valid;
    }

    /**
     * Select a subset of data that has the most connections.
     *
     * @param array<array{class: ClassDefinition, dependencies: array<Dependency>}> $parsedData
     * @param int $maxNodes Maximum number of nodes to include
     * @return array<array{class: ClassDefinition, dependencies: array<Dependency>}>
     */
    private function selectMostConnectedSubset(array $parsedData, int $maxNodes): array
    {
        // Sort by number of dependencies (most connected first)
        usort($parsedData, function ($a, $b) {
            return count($b['dependencies']) <=> count($a['dependencies']);
        });

        // Take the top N most connected classes
        return array_slice($parsedData, 0, $maxNodes);
    }

    /**
     * Find orphaned nodes (nodes with no connections).
     *
     * @param Graph $graph The graph to analyze
     * @return array<string> List of orphaned node IDs
     */
    private function findOrphanedNodes(Graph $graph): array
    {
        $orphanedNodes = [];
        $edges = $graph->getEdges();

        // Build a set of all connected nodes
        $connectedNodes = [];
        foreach ($edges as $edge) {
            $connectedNodes[$edge->source] = true;
            $connectedNodes[$edge->target] = true;
        }

        // Find nodes that don't appear in any edge
        foreach ($graph->getNodes() as $nodeId => $node) {
            if (!isset($connectedNodes[$nodeId])) {
                $orphanedNodes[] = $nodeId;
            }
        }

        return $orphanedNodes;
    }

    /**
     * Identify disconnected subgraphs within the overall graph.
     *
     * @param Graph $graph The graph to analyze
     * @return array<array<string>> List of subgraphs (each containing node IDs)
     */
    private function identifySubgraphs(Graph $graph): array
    {
        $nodes = array_keys($graph->getNodes());
        $edges = $graph->getEdges();

        // Build adjacency list
        $adjacencyList = [];
        foreach ($nodes as $nodeId) {
            $adjacencyList[$nodeId] = [];
        }

        foreach ($edges as $edge) {
            $adjacencyList[$edge->source][] = $edge->target;
            $adjacencyList[$edge->target][] = $edge->source; // Treat as undirected for connectivity
        }

        // Identify connected components using BFS
        $visited = [];
        $subgraphs = [];

        foreach ($nodes as $nodeId) {
            if (!isset($visited[$nodeId])) {
                $subgraph = [];
                $queue = [$nodeId];
                $visited[$nodeId] = true;

                while (!empty($queue)) {
                    $current = array_shift($queue);
                    $subgraph[] = $current;

                    foreach ($adjacencyList[$current] as $neighbor) {
                        if (!isset($visited[$neighbor])) {
                            $visited[$neighbor] = true;
                            $queue[] = $neighbor;
                        }
                    }
                }

                $subgraphs[] = $subgraph;
            }
        }

        return $subgraphs;
    }
}
