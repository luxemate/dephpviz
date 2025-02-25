<?php

declare(strict_types=1);

namespace DePhpViz\Command;

use DePhpViz\FileSystem\DirectoryScanner;
use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\FileSystemRepository;
use DePhpViz\Parser\Exception\MultipleClassesException;
use DePhpViz\Parser\Exception\ParserException;
use DePhpViz\Parser\PhpFileAnalyzer;
use DePhpViz\Parser\PhpFileParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze PHP dependencies in a directory'
)]
class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'The directory to analyze')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file for the graph data', 'var/graph.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Properly handle input values
        $directoryInput = $input->getArgument('directory');
        if (!is_string($directoryInput)) {
            $io->error('Directory argument must be a string.');
            return Command::INVALID;
        }
        $directory = $directoryInput;

        $outputFileInput = $input->getOption('output');
        if (!is_string($outputFileInput)) {
            $io->error('Output file option must be a string.');
            return Command::INVALID;
        }
        $outputFile = $outputFileInput;

        $io->title('DePhpViz - PHP Dependency Analyzer');

        try {
            // Create services
            $fileSystemRepository = new FileSystemRepository();
            $directoryScanner = new DirectoryScanner($fileSystemRepository);
            $phpFileParser = new PhpFileParser();
            $phpFileAnalyzer = new PhpFileAnalyzer($fileSystemRepository, $phpFileParser);

            // Scan for PHP files
            $io->section('Scanning for PHP files');
            $phpFiles = iterator_to_array($directoryScanner->scanDirectory($directory, $output));

            $fileCount = count($phpFiles);
            $io->success(sprintf('Found %d PHP files in %s', $fileCount, $directory));

            // Parse PHP files
            $io->section('Analyzing PHP files');
            $progressBar = new ProgressBar($output, $fileCount);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
            $progressBar->setMessage('Starting analysis...');
            $progressBar->start();

            $validClasses = [];
            $skippedFiles = [];
            $multipleClassesFiles = [];
            $parseErrorFiles = [];

            foreach ($phpFiles as $index => $phpFile) {
                $progressBar->setMessage(sprintf('Analyzing %s', $phpFile->relativePath));

                try {
                    $result = $phpFileAnalyzer->analyze($phpFile);

                    if ($result !== null) {
                        $validClasses[] = $result;
                    } else {
                        $skippedFiles[] = $phpFile->relativePath;
                    }
                } catch (MultipleClassesException $e) {
                    $multipleClassesFiles[] = $phpFile->relativePath;
                } catch (ParserException $e) {
                    $parseErrorFiles[] = [
                        'file' => $phpFile->relativePath,
                        'error' => $e->getMessage()
                    ];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');

            // Report results
            $validClassCount = count($validClasses);
            $io->success(sprintf('Successfully analyzed %d PHP files with a single class definition', $validClassCount));

            if (count($skippedFiles) > 0) {
                $io->info(sprintf('Skipped %d files with no class definition', count($skippedFiles)));
                if ($output->isVerbose()) {
                    $io->listing($skippedFiles);
                }
            }

            if (count($multipleClassesFiles) > 0) {
                $io->warning(sprintf('Found %d files with multiple class definitions', count($multipleClassesFiles)));
                if ($output->isVerbose()) {
                    $io->listing($multipleClassesFiles);
                }
            }

            if (count($parseErrorFiles) > 0) {
                $io->warning(sprintf('Encountered parsing errors in %d files', count($parseErrorFiles)));
                if ($output->isVerbose()) {
                    foreach ($parseErrorFiles as $errorInfo) {
                        $io->writeln(sprintf('<error>%s</error>: %s', $errorInfo['file'], $errorInfo['error']));
                    }
                }
            }

            // Future steps will implement graph construction

            return Command::SUCCESS;
        } catch (DirectoryNotFoundException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error(sprintf('An error occurred: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->section('Stack trace');
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}
