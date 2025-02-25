<?php

declare(strict_types=1);

namespace DePhpViz\Parser;

use DePhpViz\FileSystem\Model\PhpFile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Collects and manages errors encountered during parsing.
 */
class ErrorCollector
{
    /** @var array<string, array<string>> */
    private array $errors = [];

    /** @var array<string, string> */
    private array $errorTypes = [];

    /**
     * @param LoggerInterface $logger Logger for recording errors
     */
    public function __construct(
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Record an error for a specific file.
     *
     * @param PhpFile|string $file The file or file path
     * @param string $message The error message
     * @param string $type The error type (for categorization)
     */
    public function addError(PhpFile|string $file, string $message, string $type = 'general'): void
    {
        $filePath = $file instanceof PhpFile ? $file->relativePath : (string) $file;

        if (!isset($this->errors[$filePath])) {
            $this->errors[$filePath] = [];
        }

        $this->errors[$filePath][] = $message;
        $this->errorTypes[$filePath] = $type;

        // Log the error
        $this->logger->warning(sprintf('[%s] %s: %s', $type, $filePath, $message));
    }

    /**
     * Record an exception as an error.
     *
     * @param PhpFile|string $file The file or file path
     * @param \Throwable $exception The exception
     * @param string $type The error type (for categorization)
     */
    public function addException(PhpFile|string $file, \Throwable $exception, string $type = 'exception'): void
    {
        $this->addError($file, $exception->getMessage(), $type);
    }

    /**
     * Check if there are any errors for a specific file.
     *
     * @param PhpFile|string $file The file or file path
     * @return bool True if there are errors, false otherwise
     */
    public function hasErrors(PhpFile|string $file): bool
    {
        $filePath = $file instanceof PhpFile ? $file->relativePath : (string) $file;
        return isset($this->errors[$filePath]) && !empty($this->errors[$filePath]);
    }

    /**
     * Get all errors for a specific file.
     *
     * @param PhpFile|string $file The file or file path
     * @return array<string> The error messages
     */
    public function getErrors(PhpFile|string $file): array
    {
        $filePath = $file instanceof PhpFile ? $file->relativePath : (string) $file;
        return $this->errors[$filePath] ?? [];
    }

    /**
     * Get the error type for a specific file.
     *
     * @param PhpFile|string $file The file or file path
     * @return string The error type
     */
    public function getErrorType(PhpFile|string $file): string
    {
        $filePath = $file instanceof PhpFile ? $file->relativePath : (string) $file;
        return $this->errorTypes[$filePath] ?? 'unknown';
    }

    /**
     * Get all files with errors.
     *
     * @return array<string> The file paths
     */
    public function getFilesWithErrors(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Get all errors grouped by error type.
     *
     * @return array<string, array<string, array<string>>> The errors grouped by type
     */
    public function getErrorsByType(): array
    {
        $result = [];

        foreach ($this->errors as $filePath => $messages) {
            $type = $this->errorTypes[$filePath] ?? 'unknown';

            if (!isset($result[$type])) {
                $result[$type] = [];
            }

            $result[$type][$filePath] = $messages;
        }

        return $result;
    }

    /**
     * Check if there are any errors.
     *
     * @return bool True if there are errors, false otherwise
     */
    public function hasAnyErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the total number of files with errors.
     *
     * @return int The number of files with errors
     */
    public function getTotalErrorFiles(): int
    {
        return count($this->errors);
    }

    /**
     * Get the total number of error messages.
     *
     * @return int The number of error messages
     */
    public function getTotalErrorMessages(): int
    {
        $count = 0;

        foreach ($this->errors as $messages) {
            $count += count($messages);
        }

        return $count;
    }

    /**
     * Clear all errors.
     */
    public function clear(): void
    {
        $this->errors = [];
        $this->errorTypes = [];
    }
}
