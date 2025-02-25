<?php

declare(strict_types=1);

namespace DePhpViz\Parser\Model;

/**
 * Represents a dependency between classes.
 */
final readonly class Dependency
{
    /**
     * @param string $sourceClass The fully qualified name of the source class
     * @param string $targetClass The fully qualified name of the target class
     * @param string $type The type of dependency (e.g., "use", "extends", "implements")
     */
    public function __construct(
        public string $sourceClass,
        public string $targetClass,
        public string $type
    ) {
    }
}
