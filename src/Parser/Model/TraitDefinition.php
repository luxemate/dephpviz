<?php

declare(strict_types=1);

namespace DePhpViz\Parser\Model;

/**
 * Represents a trait definition found in a PHP file.
 */
final readonly class TraitDefinition extends AbstractDefinition
{
    /**
     * @param string $name The trait name (without namespace)
     * @param string $namespace The namespace
     * @param string $fullyQualifiedName The fully qualified trait name
     * @param string $filePath The file path where this trait is defined
     * @param array<string> $docComment The trait doc comment, if any
     */
    public function __construct(
        string $name,
        string $namespace,
        string $fullyQualifiedName,
        string $filePath,
        array $docComment = []
    ) {
        parent::__construct(
            $name,
            $namespace,
            $fullyQualifiedName,
            $filePath,
            $docComment,
            'trait'
        );
    }
}
