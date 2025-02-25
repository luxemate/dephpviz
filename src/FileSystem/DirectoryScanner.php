<?php

declare(strict_types=1);

namespace DePhpViz\FileSystem;

use DePhpViz\FileSystem\Contract\FileSystemRepositoryInterface;
use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\Model\PhpFile;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class DirectoryScanner
{
    /**
     * @param FileSystemRepositoryInterface $fileSystemRepository The file system repository
     */
    public function __construct(
        private readonly FileSystemRepositoryInterface $fileSystemRepository
    ) {
    }

    /**
     * Scan a directory for PHP files with progress reporting.
     *
     * @param string $directoryPath The directory to scan
     * @param OutputInterface|null $output Optional output for progress reporting
     * @return iterable<PhpFile> The PHP files found
     *
     * @throws DirectoryNotFoundException If the directory doesn't exist
     */
    public function scanDirectory(string $directoryPath, ?OutputInterface $output = null): iterable
    {
        if (!$this->fileSystemRepository->directoryExists($directoryPath)) {
            throw new DirectoryNotFoundException(
                sprintf('Directory "%s" not found', $directoryPath)
            );
        }

        // Initialize progress display if output is available
        $progressBar = null;
        $fileCount = 0;

        if ($output !== null) {
            $output->writeln(sprintf('Scanning directory: <info>%s</info>', $directoryPath));
            $progressBar = new ProgressBar($output);
            $progressBar->setFormat(' %current% files found [%bar%] %elapsed:6s%');
            $progressBar->start();
        }

        // Use the repository to find PHP files
        foreach ($this->fileSystemRepository->findPhpFiles($directoryPath) as $phpFile) {
            $fileCount++;

            if ($progressBar !== null) {
                $progressBar->setProgress($fileCount);
            }

            yield $phpFile;
        }

        if ($progressBar !== null) {
            $progressBar->finish();
            $output->writeln('');
            $output->writeln(sprintf('Found <info>%d</info> PHP files', $fileCount));
        }
    }
}
