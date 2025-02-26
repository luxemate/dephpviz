<?php

declare(strict_types=1);

namespace DePhpViz\Parser\Model;

/**
 * Represents a class definition found in a PHP file.
 */
final readonly class ClassDefinition extends AbstractDefinition
{
    /**
     * @param string $name The class name (without namespace)
     * @param string $namespace The namespace
     * @param string $fullyQualifiedName The fully qualified class name
     * @param string $filePath The file path where this class is defined
     * @param array<string> $docComment The class doc comment, if any
     * @param bool $isAbstract Whether the class is abstract
     * @param bool $isFinal Whether the class is final
     */
    public function __construct(
        string $name,
        string $namespace,
        string $fullyQualifiedName,
        string $filePath,
        array $docComment = [],
        public bool $isAbstract = false,
        public bool $isFinal = false
    ) {
        parent::__construct(
            $name,
            $namespace,
            $fullyQualifiedName,
            $filePath,
            $docComment
        );
    }
}
