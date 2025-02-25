<?php

declare(strict_types=1);

namespace DePhpViz\Command;

use DePhpViz\FileSystem\DirectoryScanner;
use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\FileSystemRepository;
use DePhpViz\Parser\ErrorCollector;
use DePhpViz\Parser\PhpFileAnalyzer;
use DePhpViz\Parser\PhpFileParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze PHP dependencies in a directory'
)]
class AnalyzeCommand extends Command
{
    private LoggerInterface $logger;

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'The directory to analyze')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file for the graph data', 'var/graph.json')
            ->addOption('log', 'l', InputOption::VALUE_OPTIONAL, 'Log file', 'var/logs/dephpviz.log')
            ->addOption('require-namespace', null, InputOption::VALUE_NONE, 'Require namespace for all PHP files')
            ->addOption('error-report', null, InputOption::VALUE_OPTIONAL, 'Generate an error report file', 'var/error-report.json');
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

        $logFileInput = $input->getOption('log');
        if (!is_string($logFileInput)) {
            $io->error('Log file option must be a string.');
            return Command::INVALID;
        }
        $logFile = $logFileInput;

        $requireNamespace = $input->getOption('require-namespace') === true;

        $errorReportInput = $input->getOption('error-report');
        $errorReport = is_string($errorReportInput) ? $errorReportInput : null;

        $io->title('DePhpViz - PHP Dependency Analyzer');

        try {
            // Set up logging
            $this->configureLogger($output, $logFile);

            // Create services
            $fileSystemRepository = new FileSystemRepository();
            $directoryScanner = new DirectoryScanner($fileSystemRepository);
            $errorCollector = new ErrorCollector($this->logger);
            $phpFileParser = new PhpFileParser();
            $phpFileAnalyzer = new PhpFileAnalyzer(
                $fileSystemRepository,
                $phpFileParser,
                $errorCollector,
                $this->logger
            );

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

            foreach ($phpFiles as $index => $phpFile) {
                $progressBar->setMessage(sprintf('Analyzing %s', $phpFile->relativePath));

                $result = $phpFileAnalyzer->analyze($phpFile, $requireNamespace);

                if ($result !== null) {
                    $validClasses[] = $result;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');

            // Report results
            $validClassCount = count($validClasses);
            $io->success(sprintf('Successfully analyzed %d PHP files with a single class definition', $validClassCount));

            // Display error statistics by type
            if ($errorCollector->hasAnyErrors()) {
                $errorsByType = $errorCollector->getErrorsByType();

                $io->section(sprintf('Encountered %d files with errors', $errorCollector->getTotalErrorFiles()));

                $table = new Table($output);
                $table->setHeaderTitle('Error Summary');
                $table->setHeaders(['Error Type', 'Count']);

                foreach ($errorsByType as $type => $errors) {
                    $table->addRow([
                        $type,
                        count($errors)
                    ]);
                }

                $table->render();

                // Show the first few errors of each type
                if ($output->isVerbose()) {
                    foreach ($errorsByType as $type => $fileErrors) {
                        $io->section(sprintf('%s Errors', ucfirst(str_replace('_', ' ', $type))));

                        $counter = 0;
                        foreach ($fileErrors as $file => $messages) {
                            if ($counter++ >= 5 && !$output->isVeryVerbose()) {
                                $io->writeln(sprintf('<info>... and %d more files</info>', count($fileErrors) - 5));
                                break;
                            }

                            $io->writeln(sprintf('<error>%s</error>:', $file));
                            foreach ($messages as $message) {
                                $io->writeln(sprintf(' - %s', $message));
                            }
                        }
                    }
                }

                // Generate error report if requested
                if ($errorReport !== null) {
                    $this->generateErrorReport($errorReport, $errorCollector);
                    $io->info(sprintf('Error report saved to %s', $errorReport));
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
            if (isset($this->logger)) {
                $this->logger->critical('Unhandled exception', [
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return Command::FAILURE;
        }
    }

    /**
     * Configure the logger.
     *
     * @param OutputInterface $output Console output
     * @param string $logFile Log file path
     */
    private function configureLogger(OutputInterface $output, string $logFile): void
    {
        // Ensure the log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger = new \Monolog\Logger('dephpviz');

        // Add file handler
        $fileHandler = new \Monolog\Handler\StreamHandler(
            $logFile,
            \Monolog\Level::Debug
        );
        $logger->pushHandler($fileHandler);

        // Add console handler
        $consoleHandler = new \DePhpViz\Util\ConsoleHandler(
            $output,
            $output->isVerbose()
                ? \Monolog\Level::Info
                : \Monolog\Level::Warning
        );
        $logger->pushHandler($consoleHandler);

        // Add processor for additional context information
        $logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
        $logger->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());

        $this->logger = $logger;
    }

    /**
     * Generate an error report file.
     *
     * @param string $filePath The output file path
     * @param ErrorCollector $errorCollector The error collector
     */
    private function generateErrorReport(string $filePath, ErrorCollector $errorCollector): void
    {
        // Ensure the directory exists
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $report = [
            'summary' => [
                'total_files_with_errors' => $errorCollector->getTotalErrorFiles(),
                'total_error_messages' => $errorCollector->getTotalErrorMessages(),
                'generated_at' => date('Y-m-d H:i:s'),
            ],
            'errors_by_type' => $errorCollector->getErrorsByType()
        ];

        file_put_contents($filePath, json_encode($report, JSON_PRETTY_PRINT));
    }
}
