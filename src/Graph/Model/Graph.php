<?php

declare(strict_types=1);

namespace DePhpViz\Graph\Model;

/**
 * Represents the entire dependency graph.
 */
class Graph
{
    /** @var array<string, Node> */
    private array $nodes = [];

    /** @var array<string, Edge> */
    private array $edges = [];

    /**
     * Add a node to the graph.
     *
     * @param Node $node The node to add
     * @return self
     */
    public function addNode(Node $node): self
    {
        $this->nodes[$node->id] = $node;
        return $this;
    }

    /**
     * Add an edge to the graph.
     *
     * @param Edge $edge The edge to add
     * @return self
     */
    public function addEdge(Edge $edge): self
    {
        // Only add the edge if both source and target nodes exist
        if (isset($this->nodes[$edge->source]) && isset($this->nodes[$edge->target])) {
            $this->edges[$edge->id] = $edge;
        }

        return $this;
    }

    /**
     * Check if a node exists.
     *
     * @param string $id The node ID
     * @return bool
     */
    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * Get a node by ID.
     *
     * @param string $id The node ID
     * @return Node|null
     */
    public function getNode(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * Get all nodes.
     *
     * @return array<string, Node>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Get all edges.
     *
     * @return array<string, Edge>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * Convert to array representation for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(fn (Node $node) => $node->toArray(), $this->nodes),
            'edges' => array_map(fn (Edge $edge) => $edge->toArray(), $this->edges),
        ];
    }

    /**
     * Convert to JSON.
     *
     * @param int $options JSON encode options
     * @return string
     * @throws \JsonException If encoding fails
     */
    public function toJson(int $options = 0): string
    {
        return json_encode(
            $this->toArray(),
            $options | JSON_THROW_ON_ERROR
        );
    }

}
