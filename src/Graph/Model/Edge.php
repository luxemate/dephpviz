<?php

declare(strict_types=1);

namespace DePhpViz\Graph\Model;

/**
 * Represents an edge (relationship) in the dependency graph.
 */
final readonly class Edge
{
    /**
     * @param string $id Unique identifier for the edge
     * @param string $source Source node ID
     * @param string $target Target node ID
     * @param string $type Type of relationship ('use', 'extends', 'implements', 'usesTrait')
     * @param array<string, mixed> $metadata Additional edge metadata
     */
    public function __construct(
        public string $id,
        public string $source,
        public string $target,
        public string $type,
        public array $metadata = []
    ) {
    }

    /**
     * Create an edge from a dependency.
     *
     * @param \DePhpViz\Parser\Model\Dependency $dependency
     * @return self
     */
    public static function fromDependency(\DePhpViz\Parser\Model\Dependency $dependency): self
    {
        return new self(
            $dependency->sourceClass . '->' . $dependency->targetClass,
            $dependency->sourceClass,
            $dependency->targetClass,
            $dependency->type,
            []
        );
    }

    /**
     * Convert to array representation for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'type' => $this->type,
            'metadata' => $this->metadata,
        ];
    }
}
