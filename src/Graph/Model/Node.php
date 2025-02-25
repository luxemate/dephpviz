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
     * @param array<string, mixed> $metadata Additional node metadata
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $metadata = []
    ) {
    }

    /**
     * Create a node from a class definition.
     *
     * @param \DePhpViz\Parser\Model\ClassDefinition $classDefinition
     * @return self
     */
    public static function fromClassDefinition(\DePhpViz\Parser\Model\ClassDefinition $classDefinition): self
    {
        return new self(
            $classDefinition->fullyQualifiedName,
            $classDefinition->name,
            [
                'namespace' => $classDefinition->namespace,
                'filePath' => $classDefinition->filePath,
                'isAbstract' => $classDefinition->isAbstract,
                'isFinal' => $classDefinition->isFinal,
                'docComment' => $classDefinition->docComment,
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
            'metadata' => $this->metadata,
        ];
    }
}
