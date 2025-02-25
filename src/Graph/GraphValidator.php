<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Graph\Model\Edge;
use DePhpViz\Graph\Model\Graph;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Validates graph structure and identifies potential issues.
 */
class GraphValidator
{
    /**
     * @param LoggerInterface $logger Logger for recording validation information
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Validate the graph and identify potential issues.
     *
     * @param Graph $graph The graph to validate
     * @return array{
     *     orphanedNodes: list<string>,
     *     multipleInheritance: array<string, list<string>>,
     *     circularDependencies: list<list<string>>,
     *     longestPaths: list<list<string>>,
     *     mostConnected: array<string, array<string, int>>,
     *     leastConnected: array<string, array<string, int>>,
     *     subgraphCount: int,
     *     largestSubgraph: int,
     *     smallestSubgraph: int,
     *     isValid: bool,
     * } Validation results
     */
    public function validate(Graph $graph): array
    {
        $this->logger->info('Validating graph structure');

        $results = [
            'orphanedNodes' => [],
            'multipleInheritance' => [],
            'circularDependencies' => [],
            'longestPaths' => [],
            'mostConnected' => [],
            'leastConnected' => [],
            'subgraphCount' => 0,
            'largestSubgraph' => 0,
            'smallestSubgraph' => 0,
            'isValid' => true
        ];

        // Find orphaned nodes (no connections)
        $orphanedNodes = $this->findOrphanedNodes($graph);
        $results['orphanedNodes'] = $orphanedNodes;

        // Check for multiple inheritance (should not happen in PHP)
        $multipleInheritance = $this->findMultipleInheritance($graph);
        $results['multipleInheritance'] = $multipleInheritance;

        // Detect circular dependencies in extends/implements
        $circularDependencies = $this->findCircularDependencies($graph);
        $results['circularDependencies'] = $circularDependencies;

        // Find the longest dependency paths
        $longestPaths = $this->findLongestPaths($graph);
        $results['longestPaths'] = $longestPaths;

        // Find the most connected nodes
        $nodeConnections = $this->calculateNodeConnections($graph);
        $results['mostConnected'] = $this->getTopNodesByConnections($nodeConnections, 5, true);
        $results['leastConnected'] = $this->getTopNodesByConnections($nodeConnections, 5, false);

        // Identify disconnected subgraphs
        $subgraphs = $this->identifySubgraphs($graph);
        $results['subgraphCount'] = count($subgraphs);

        if (!empty($subgraphs)) {
            $subgraphSizes = array_map('count', $subgraphs);
            $results['largestSubgraph'] = max($subgraphSizes);
            $results['smallestSubgraph'] = min($subgraphSizes);
        }

        // Determine if the graph is valid overall
        $results['isValid'] = empty($multipleInheritance) && empty($circularDependencies);

        $this->logger->info(sprintf(
            'Graph validation completed: %s',
            $results['isValid'] ? 'Valid' : 'Issues detected'
        ));

        return $results;
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
     * Find cases of multiple inheritance (which shouldn't happen in PHP).
     *
     * @param Graph $graph The graph to analyze
     * @return array<string, array<string>> Map of nodes with multiple parents
     */
    private function findMultipleInheritance(Graph $graph): array
    {
        $multipleInheritance = [];
        $parentMap = [];

        // Build a map of child -> [parents]
        foreach ($graph->getEdges() as $edge) {
            if ($edge->type === 'extends') {
                if (!isset($parentMap[$edge->source])) {
                    $parentMap[$edge->source] = [];
                }
                $parentMap[$edge->source][] = $edge->target;
            }
        }

        // Find children with multiple parents
        foreach ($parentMap as $child => $parents) {
            if (count($parents) > 1) {
                $multipleInheritance[$child] = $parents;
            }
        }

        return $multipleInheritance;
    }

    /**
     * Find circular dependencies in inheritance relationships.
     *
     * @param Graph $graph The graph to analyze
     * @return array<array<string>> List of circular paths
     */
    private function findCircularDependencies(Graph $graph): array
    {
        $circularPaths = [];
        $inheritanceEdges = [];

        // Filter to only inheritance edges
        foreach ($graph->getEdges() as $edge) {
            if ($edge->type === 'extends' || $edge->type === 'implements') {
                $inheritanceEdges[] = $edge;
            }
        }

        // Find cycles using DFS
        $visited = [];
        $recursionStack = [];

        foreach ($graph->getNodes() as $nodeId => $node) {
            if (!isset($visited[$nodeId])) {
                $this->detectCycles(
                    $nodeId,
                    $visited,
                    $recursionStack,
                    $inheritanceEdges,
                    [],
                    $circularPaths
                );
            }
        }

        return $circularPaths;
    }

    /**
     * Detect cycles in the graph using DFS.
     *
     * @param string $currentNode Current node being explored
     * @param array<string, bool> $visited Nodes already visited
     * @param array<string, bool> $recursionStack Nodes in current exploration path
     * @param array<\DePhpViz\Graph\Model\Edge> $inheritanceEdges Edges representing inheritance
     * @param array<string> $currentPath Current path being explored
     * @param array<array<string>> $circularPaths Output parameter for detected cycles
     */
    private function detectCycles(
        string $currentNode,
        array &$visited,
        array &$recursionStack,
        array $inheritanceEdges,
        array $currentPath,
        array &$circularPaths
    ): void {
        $visited[$currentNode] = true;
        $recursionStack[$currentNode] = true;
        $currentPath[] = $currentNode;

        // Find all outgoing edges from the current node
        foreach ($inheritanceEdges as $edge) {
            if ($edge->source !== $currentNode) {
                continue;
            }

            $target = $edge->target;

            // If the target is in the recursion stack, we found a cycle
            if (isset($recursionStack[$target])) {
                // Find the starting point of the cycle in the current path
                $cycleStart = array_search($target, $currentPath);
                if ($cycleStart !== false) {
                    $cycle = array_slice($currentPath, (int)$cycleStart);
                    $cycle[] = $target; // Complete the cycle
                    $circularPaths[] = $cycle;
                }
            } elseif (!isset($visited[$target])) {
                $this->detectCycles(
                    $target,
                    $visited,
                    $recursionStack,
                    $inheritanceEdges,
                    $currentPath,
                    $circularPaths
                );
            }
        }

        // Remove the current node from recursion stack when done
        unset($recursionStack[$currentNode]);
    }

    /**
     * Find the longest dependency paths in the graph.
     *
     * @param Graph $graph The graph to analyze
     * @param int $limit Maximum number of paths to return
     * @return array<array<string>> List of longest paths
     */
    private function findLongestPaths(Graph $graph, int $limit = 5): array
    {
        $adjacencyList = [];

        // Build adjacency list
        foreach ($graph->getEdges() as $edge) {
            if (!isset($adjacencyList[$edge->source])) {
                $adjacencyList[$edge->source] = [];
            }
            $adjacencyList[$edge->source][] = $edge->target;
        }

        // Find all paths and their lengths
        $allPaths = [];

        foreach ($graph->getNodes() as $nodeId => $node) {
            if (!isset($adjacencyList[$nodeId])) {
                continue; // Skip nodes with no outgoing edges
            }

            $paths = $this->findAllPaths($nodeId, $adjacencyList);
            foreach ($paths as $path) {
                $allPaths[] = $path;
            }
        }

        // Sort paths by length (descending)
        usort($allPaths, function ($a, $b) {
            return count($b) <=> count($a);
        });

        // Return the top N longest paths
        return array_slice($allPaths, 0, $limit);
    }

    /**
     * Find all paths starting from a node using DFS.
     *
     * @param string $start Starting node
     * @param array<string, array<string>> $adjacencyList Adjacency list representation
     * @param int $maxDepth Maximum path depth to prevent infinite recursion
     * @return array<array<string>> All paths from the starting node
     */
    private function findAllPaths(string $start, array $adjacencyList, int $maxDepth = 20): array
    {
        $visited = [];
        $paths = [];
        $this->dfs($start, $adjacencyList, [$start], $visited, $paths, $maxDepth);
        return $paths;
    }

    /**
     * Depth-first search to find all paths.
     *
     * @param string $current Current node
     * @param array<string, array<string>> $adjacencyList Adjacency list
     * @param array<string> $path Current path
     * @param array<string, bool> $visited Visited nodes
     * @param array<array<string>> $paths All found paths
     * @param int $maxDepth Maximum depth
     * @param int $depth Current depth
     */
    private function dfs(
        string $current,
        array $adjacencyList,
        array $path,
        array &$visited,
        array &$paths,
        int $maxDepth,
        int $depth = 0
    ): void {
        if ($depth >= $maxDepth) {
            return;
        }

        $visited[$current] = true;

        // If this node has no outgoing edges, we've reached the end of a path
        if (!isset($adjacencyList[$current]) || empty($adjacencyList[$current])) {
            $paths[] = $path;
        } else {
            foreach ($adjacencyList[$current] as $neighbor) {
                // Avoid cycles
                if (in_array($neighbor, $path)) {
                    continue;
                }

                $newPath = array_merge($path, [$neighbor]);
                $this->dfs($neighbor, $adjacencyList, $newPath, $visited, $paths, $maxDepth, $depth + 1);
            }
        }

        // Backtrack
        unset($visited[$current]);
    }

    /**
     * Calculate the number of connections for each node.
     *
     * @param Graph $graph The graph to analyze
     * @return array<string, array{in: int, out: int, total: int}> Connection counts per node
     */
    private function calculateNodeConnections(Graph $graph): array
    {
        $connections = [];

        // Initialize connection counts for all nodes
        foreach ($graph->getNodes() as $nodeId => $node) {
            $connections[$nodeId] = [
                'in' => 0,
                'out' => 0,
                'total' => 0
            ];
        }

        // Count connections
        foreach ($graph->getEdges() as $edge) {
            if (isset($connections[$edge->source])) {
                $connections[$edge->source]['out']++;
                $connections[$edge->source]['total']++;
            }

            if (isset($connections[$edge->target])) {
                $connections[$edge->target]['in']++;
                $connections[$edge->target]['total']++;
            }
        }

        return $connections;
    }

    /**
     * Get the top N nodes by connection count.
     *
     * @param array<string, array{in: int, out: int, total: int}> $nodeConnections
     * @param int $limit Maximum number of nodes to return
     * @param bool $mostConnected If true, return most connected; if false, least connected
     * @return array<string, array{in: int, out: int, total: int}> Top nodes by connection count
     */
    private function getTopNodesByConnections(array $nodeConnections, int $limit, bool $mostConnected = true): array
    {
        uasort($nodeConnections, function ($a, $b) use ($mostConnected) {
            if ($mostConnected) {
                return $b['total'] <=> $a['total'];
            } else {
                return $a['total'] <=> $b['total'];
            }
        });

        return array_slice($nodeConnections, 0, $limit, true);
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

        // Build adjacency list (treating all edges as undirected for connectivity)
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
