<?php

declare(strict_types=1);

namespace DePhpViz\Command;

use DePhpViz\FileSystem\DirectoryScanner;
use DePhpViz\FileSystem\Exception\DirectoryNotFoundException;
use DePhpViz\FileSystem\FileSystemRepository;
use DePhpViz\Graph\DependencyMapper;
use DePhpViz\Graph\GraphBuilder;
use DePhpViz\Graph\GraphSerializer;
use DePhpViz\Graph\GraphValidator;
use DePhpViz\Parser\ErrorCollector;
use DePhpViz\Parser\PhpFileAnalyzer;
use DePhpViz\Parser\PhpFileParser;
use DePhpViz\Util\ConsoleHandler;
use DePhpViz\Web\WebServer;
use DePhpViz\Web\WebServerConfig;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze PHP dependencies in a directory'
)]
class AnalyzeCommand extends Command
{
    private Logger $logger;

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'The directory to analyze')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file for the graph data', 'var/graph.json')
            ->addOption('log', 'l', InputOption::VALUE_OPTIONAL, 'Log file', 'var/logs/dephpviz.log')
            ->addOption('require-namespace', null, InputOption::VALUE_NONE, 'Require namespace for all PHP files')
            ->addOption('error-report', null, InputOption::VALUE_OPTIONAL, 'Generate an error report file', 'var/error-report.json')
            ->addOption('validate', null, InputOption::VALUE_NONE, 'Perform graph validation')
            ->addOption('validation-report', null, InputOption::VALUE_OPTIONAL, 'Generate a validation report file', 'var/validation-report.json');
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

        $performValidation = $input->getOption('validate') === true;

        $validationReportInput = $input->getOption('validation-report');
        $validationReport = is_string($validationReportInput) ? $validationReportInput : null;

        $io->title('DePhpViz - PHP Dependency Analyzer');

        try {
            // Set up logging
            $this->configureLogger($output, $logFile);

            // Create services
            $filesystem = new Filesystem();
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
            $dependencyMapper = new DependencyMapper($this->logger);
            $graphBuilder = new GraphBuilder($dependencyMapper, $this->logger);
            $graphSerializer = new GraphSerializer($filesystem, $this->logger);
            $graphValidator = new GraphValidator($this->logger);

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

            $validDefinitions = []; // Renamed from validClasses

            foreach ($phpFiles as $phpFile) {
                $progressBar->setMessage(sprintf('Analyzing %s', $phpFile->relativePath));

                $result = $phpFileAnalyzer->analyze($phpFile, $requireNamespace);

                if ($result !== null) {
                    $validDefinitions[] = $result; // Add definition + dependencies
                }

                $progressBar->advance();
            }

            // Report analysis results
            $validDefinitionCount = count($validDefinitions);
            $io->success(sprintf('Successfully analyzed %d PHP files with valid definitions', $validDefinitionCount));

            // Display error statistics
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

                // Generate error report if requested
                if ($errorReport !== null) {
                    $this->generateErrorReport($errorReport, $errorCollector);
                    $io->info(sprintf('Error report saved to %s', $errorReport));
                }
            }

            // Build the complete graph with refined dependency mapping
            $io->section('Building dependency graph');
            $result = $graphBuilder->buildGraph($validDefinitions);
            $graph = $result['graph'];
            $stats = $result['stats'];

            $nodeCount = $stats['nodeCount'];
            $edgeCount = $stats['edgeCount'];

            $io->info(sprintf(
                'Graph contains %d nodes and %d edges',
                $nodeCount,
                $edgeCount
            ));

            // Display dependency statistics
            /** @var array{
             *   use: array{count: int, missing: int, invalid: int, circular: int},
             *   extends: array{count: int, missing: int, invalid: int, circular: int},
             *   implements: array{count: int, missing: int, invalid: int, circular: int},
             *   total: array{count: int, missing: int, invalid: int, circular: int}
             * } $depStats
             */
            $depStats = $stats['dependencies'];

            $depTable = new Table($output);
            $depTable->setHeaderTitle('Dependency Mapping Statistics');
            $depTable->setHeaders(['Type', 'Mapped', 'Missing', 'Invalid', 'Circular']);

            foreach (['use', 'extends', 'implements'] as $type) {
                $depTable->addRow([
                    $type,
                    $depStats[$type]['count'],
                    $depStats[$type]['missing'],
                    $depStats[$type]['invalid'],
                    $depStats[$type]['circular']
                ]);
            }

            $depTable->addRow([
                'Total',
                $depStats['total']['count'],
                $depStats['total']['missing'],
                $depStats['total']['invalid'],
                $depStats['total']['circular']
            ]);

            $depTable->render();

            // For the validation results section, similarly add type assertions:
            if ($performValidation) {
                $io->section('Validating graph structure');
                /** @var array{
                 *     orphanedNodes: list<string>,
                 *     multipleInheritance: array<string, list<string>>,
                 *     circularDependencies: list<list<string>>,
                 *     longestPaths: list<list<string>>,
                 *     mostConnected: array<string, array<string, int>>,
                 *     leastConnected: array<string, array<string, int>>,
                 *     subgraphCount: int,
                 *     largestSubgraph: int,
                 *     smallestSubgraph: int,
                 *     isValid: bool,
                 * } $validationResults
                 */
                $validationResults = $graphValidator->validate($graph);

                // Type-safe reporting
                $io->info(sprintf(
                    'Graph validation result: %s',
                    $validationResults['isValid'] ? 'Valid' : 'Issues detected'
                ));

                if (!empty($validationResults['orphanedNodes'])) {
                    $io->info(sprintf(
                        'Found %d orphaned nodes (classes with no dependencies)',
                        count($validationResults['orphanedNodes'])
                    ));

                    if ($output->isVerbose() && count($validationResults['orphanedNodes']) > 0) {
                        $io->text('Sample of orphaned nodes:');
                        $io->listing(array_slice(
                            $validationResults['orphanedNodes'],
                            0,
                            min(5, count($validationResults['orphanedNodes']))
                        ));
                    }
                }

                if (!empty($validationResults['multipleInheritance'])) {
                    $io->warning(sprintf(
                        'Found %d cases of multiple inheritance (should not happen in PHP)',
                        count($validationResults['multipleInheritance'])
                    ));

                    if ($output->isVerbose() && count($validationResults['multipleInheritance']) > 0) {
                        foreach ($validationResults['multipleInheritance'] as $child => $parents) {
                            $io->text(sprintf(
                                'Class %s extends multiple classes: %s',
                                $child,
                                implode(', ', $parents)
                            ));
                        }
                    }
                }

                if (!empty($validationResults['circularDependencies'])) {
                    $io->warning(sprintf(
                        'Found %d circular inheritance paths',
                        count($validationResults['circularDependencies'])
                    ));

                    if ($output->isVerbose() && count($validationResults['circularDependencies']) > 0) {
                        $io->text('Circular paths:');
                        foreach ($validationResults['circularDependencies'] as $index => $path) {
                            $io->text(sprintf(
                                'Path %d: %s',
                                $index + 1,
                                implode(' -> ', $path)
                            ));
                        }
                    }
                }

                if ($validationResults['subgraphCount'] > 1) {
                    $io->info(sprintf(
                        'Graph contains %d disconnected subgraphs (largest: %d nodes, smallest: %d nodes)',
                        $validationResults['subgraphCount'],
                        $validationResults['largestSubgraph'],
                        $validationResults['smallestSubgraph']
                    ));
                }

                // Generate validation report if requested
                if ($validationReport !== null) {
                    $this->generateValidationReport($validationReport, $validationResults);
                    $io->info(sprintf('Validation report saved to %s', $validationReport));
                }
            }

            // Serialize the graph
            $io->section('Serializing graph');
            $success = $graphSerializer->serializeToJson($graph, $outputFile);

            // Add after graph serialization
            if ($success) {
                $io->success(sprintf('Graph data saved to %s', $outputFile));

                // Offer to start the web server
                if ($io->confirm('Would you like to view the visualization now?', true)) {
                    // Create the web server config
                    $webServerConfig = new WebServerConfig(
                        host: '127.0.0.1',
                        port: 8080,
                        publicDir: 'public',
                        graphDataFile: $outputFile
                    );

                    // Create and start the web server
                    $webServer = new WebServer($webServerConfig, $this->logger);
                    $webServer->start();

                    $io->success(sprintf(
                        'Web server started at %s',
                        $webServerConfig->getVisualizationUrl()
                    ));

                    // Open the browser
                    $this->openBrowser($webServerConfig->getVisualizationUrl(), $io);

                    $io->note('Press Ctrl+C to stop the web server when you\'re done');

                    // Keep the command running until interrupted
                    // @phpstan-ignore-next-line
                    while (true) {
                        sleep(1);
                    }
                }
            } else {
                $io->error('Failed to serialize graph data');
                return Command::FAILURE;
            }

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

        $logger = new Logger('dephpviz');

        // Add file handler
        $fileHandler = new StreamHandler(
            $logFile,
            Level::Debug
        );
        $logger->pushHandler($fileHandler);

        // Add console handler
        $consoleHandler = new ConsoleHandler(
            $output,
            $output->isVerbose()
                ? Level::Info
                : Level::Warning
        );
        $logger->pushHandler($consoleHandler);

        // Add processors for additional context information
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());

        $this->logger = $logger;
    }

    /**
     * Generate an error report file.
     *
     * @param string $filePath The output file path
     * @param ErrorCollector $errorCollector The error collector
     * @throws \JsonException If JSON encoding fails
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

        file_put_contents(
            $filePath,
            json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Generate a validation report file.
     *
     * @param string $filePath The output file path
     * @param array{
     *      orphanedNodes: list<string>,
     *      multipleInheritance: array<string, list<string>>,
     *      circularDependencies: list<list<string>>,
     *      longestPaths: list<list<string>>,
     *      mostConnected: array<string, array<string, int>>,
     *      leastConnected: array<string, array<string, int>>,
     *      subgraphCount: int,
     *      largestSubgraph: int,
     *      smallestSubgraph: int,
     *      isValid: bool,
     * } $validationResults The validation results
     *
     * @throws \JsonException If JSON encoding fails
     */
    private function generateValidationReport(string $filePath, array $validationResults): void
    {
        // Ensure the directory exists
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $report = [
            'summary' => [
                'is_valid' => $validationResults['isValid'],
                'orphaned_nodes_count' => count($validationResults['orphanedNodes']),
                'multiple_inheritance_count' => count($validationResults['multipleInheritance']),
                'circular_dependencies_count' => count($validationResults['circularDependencies']),
                'subgraph_count' => $validationResults['subgraphCount'],
                'generated_at' => date('Y-m-d H:i:s'),
            ],
            'details' => $validationResults
        ];

        file_put_contents(
            $filePath,
            json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Open the browser with the given URL.
     */
    private function openBrowser(string $url, SymfonyStyle $io): void
    {
        $io->section('Opening browser');

        $command = match (PHP_OS_FAMILY) {
            'Windows' => ['cmd', '/c', 'start', $url],
            'Darwin' => ['open', $url],
            default => ['xdg-open', $url]
        };

        $process = new \Symfony\Component\Process\Process($command);
        $process->start();

        $io->text(sprintf('Opening %s in your browser', $url));
    }
}
