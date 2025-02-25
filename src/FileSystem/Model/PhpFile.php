<?php

declare(strict_types=1);

namespace DePhpViz\FileSystem\Model;

/**
 * Represents a PHP file in the file system.
 */
final readonly class PhpFile
{
    /**
     * @param string $path The absolute file path
     * @param string $relativePath The path relative to the scanned directory
     */
    public function __construct(
        public string $path,
        public string $relativePath
    ) {
    }

    /**
     * Get the filename without path.
     */
    public function getFilename(): string
    {
        return basename($this->path);
    }

    /**
     * Check if this file is likely a PHP file based on extension.
     */
    public function hasPhpExtension(): bool
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'php';
    }
}
