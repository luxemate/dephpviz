<?php

declare(strict_types=1);

namespace DePhpViz\Graph\Model;

/**
 * Represents a node in the dependency graph.
 */
final readonly class Node
{
    /**
     * @param string $id Unique identifier for the node
     * @param string $label Display label for the node
     * @param string $type Type of node ('class', 'trait', or 'interface')
     * @param array<string, mixed> $metadata Additional node metadata
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $type = 'class',
        public array $metadata = []
    ) {
    }

    /**
     * Create a node from a definition.
     *
     * @param \DePhpViz\Parser\Model\AbstractDefinition $definition
     * @return self
     */
    public static function fromDefinition(\DePhpViz\Parser\Model\AbstractDefinition $definition): self
    {
        return new self(
            $definition->fullyQualifiedName,
            $definition->name,
            $definition->type,
            [
                'namespace' => $definition->namespace,
                'filePath' => $definition->filePath,
                'docComment' => $definition->docComment,
                // Add class-specific properties if available
                'isAbstract' => $definition instanceof \DePhpViz\Parser\Model\ClassDefinition ? $definition->isAbstract : false,
                'isFinal' => $definition instanceof \DePhpViz\Parser\Model\ClassDefinition ? $definition->isFinal : false,
            ]
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
            'label' => $this->label,
            'type' => $this->type,
            'metadata' => $this->metadata,
        ];
    }
}
