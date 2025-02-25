<?php

declare(strict_types=1);

namespace DePhpViz\FileSystem\Contract;

use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\Exception\FileSystemException;
use DePhpViz\FileSystem\Model\PhpFile;

interface FileSystemRepositoryInterface
{
    /**
     * Check if a directory exists.
     *
     * @param string $path The directory path
     * @return bool True if the directory exists, false otherwise
     */
    public function directoryExists(string $path): bool;

    /**
     * Get all PHP files in a directory and its subdirectories.
     *
     * @param string $directoryPath The path to scan
     * @return iterable<PhpFile> An iterable of PhpFile objects
     *
     * @throws DirectoryNotFoundException If the directory doesn't exist
     * @throws FileSystemException If there's an error accessing the file system
     */
    public function findPhpFiles(string $directoryPath): iterable;

    /**
     * Read the contents of a file.
     *
     * @param string $filePath The file path
     * @return string The file contents
     *
     * @throws FileSystemException If there's an error reading the file
     */
    public function readFile(string $filePath): string;
}
