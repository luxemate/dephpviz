<?php

declare(strict_types=1);

namespace DePhpViz\Parser\Model;

/**
 * Base class for PHP type definitions (class, trait, interface).
 */
readonly abstract class AbstractDefinition
{
    /**
     * @param string $name The definition name (without namespace)
     * @param string $namespace The namespace
     * @param string $fullyQualifiedName The fully qualified definition name
     * @param string $filePath The file path where this definition is defined
     * @param array<string> $docComment The definition doc comment, if any
     * @param string $type The type of definition ('class', 'trait', or 'interface')
     */
    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly string $fullyQualifiedName,
        public readonly string $filePath,
        public readonly array $docComment = [],
        public readonly string $type = 'class'
    ) {
    }
}
