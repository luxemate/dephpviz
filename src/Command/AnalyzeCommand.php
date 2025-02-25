<?php

declare(strict_types=1);

namespace DePhpViz\Command;

use DePhpViz\FileSystem\DirectoryScanner;
use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\FileSystemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
            // Create the file system services
            $fileSystemRepository = new FileSystemRepository();
            $directoryScanner = new DirectoryScanner($fileSystemRepository);

            // Scan for PHP files
            $io->section('Scanning for PHP files');
            $phpFiles = iterator_to_array($directoryScanner->scanDirectory($directory, $output));

            $fileCount = count($phpFiles);
            $io->success(sprintf('Found %d PHP files in %s', $fileCount, $directory));

            // Future steps will implement PHP parsing and graph construction

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
