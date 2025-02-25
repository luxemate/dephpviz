<?php

declare(strict_types=1);

namespace DePhpViz\FileSystem;

use DePhpViz\FileSystem\Contract\FileSystemRepositoryInterface;
use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\Exception\FileSystemException;
use DePhpViz\FileSystem\Model\PhpFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use FilesystemIterator;

class FileSystemRepository implements FileSystemRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function directoryExists(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * {@inheritdoc}
     */
    public function findPhpFiles(string $directoryPath): iterable
    {
        if (!$this->directoryExists($directoryPath)) {
            throw new DirectoryNotFoundException(
                sprintf('Directory "%s" not found', $directoryPath)
            );
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directoryPath,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
                )
            );

            $basePathLength = strlen(rtrim($directoryPath, DIRECTORY_SEPARATOR)) + 1;

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                // Skip directories (even though SKIP_DOTS should handle this)
                if ($fileInfo->isDir()) {
                    continue;
                }

                // Check if it's a PHP file
                if (strtolower($fileInfo->getExtension()) !== 'php') {
                    continue;
                }

                $absolutePath = $fileInfo->getRealPath();
                if ($absolutePath === false) {
                    // Skip files that can't be resolved (e.g., broken symlinks)
                    continue;
                }

                $relativePath = substr($absolutePath, $basePathLength);

                yield new PhpFile($absolutePath, $relativePath);
            }
        } catch (\Exception $e) {
            throw new FileSystemException(
                sprintf('Error scanning directory "%s": %s', $directoryPath, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readFile(string $filePath): string
    {
        try {
            $content = file_get_contents($filePath);

            if ($content === false) {
                throw new FileSystemException(
                    sprintf('Could not read file "%s"', $filePath)
                );
            }

            return $content;
        } catch (\Exception $e) {
            throw new FileSystemException(
                sprintf('Error reading file "%s": %s', $filePath, $e->getMessage()),
                0,
                $e
            );
        }
    }
}
