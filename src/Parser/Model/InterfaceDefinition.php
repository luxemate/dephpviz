<?php

declare(strict_types=1);

namespace DePhpViz\Parser\Model;

/**
 * Represents an interface definition found in a PHP file.
 */
final readonly class InterfaceDefinition extends AbstractDefinition
{
    /**
     * @param string $name The interface name (without namespace)
     * @param string $namespace The namespace
     * @param string $fullyQualifiedName The fully qualified interface name
     * @param string $filePath The file path where this interface is defined
     * @param array<string> $docComment The interface doc comment, if any
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
            'interface'
        );
    }
}
