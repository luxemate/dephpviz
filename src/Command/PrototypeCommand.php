<?php

declare(strict_types=1);

namespace DePhpViz\Command;

use DePhpViz\Graph\GraphBuilder;
use DePhpViz\Graph\GraphSerializer;
use DePhpViz\Graph\GraphTester;
use DePhpViz\Graph\SampleDataGenerator;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'prototype',
    description: 'Test graph creation with a generated sample dataset'
)]
class PrototypeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('nodes', null, InputOption::VALUE_OPTIONAL, 'Number of nodes to generate', '50')
            ->addOption('connectivity', null, InputOption::VALUE_OPTIONAL, 'Connectivity factor (0.0-1.0)', '0.3')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file for the graph data', 'var/prototype-graph.json')
            ->addOption('hierarchical', null, InputOption::VALUE_NONE, 'Generate a hierarchical graph instead of a network')
            ->addOption('depth', null, InputOption::VALUE_OPTIONAL, 'Depth of the hierarchical graph', '3')
            ->addOption('width', null, InputOption::VALUE_OPTIONAL, 'Width of the hierarchical graph', '3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse options with proper validation
        $nodeCountInput = $input->getOption('nodes');
        if (!is_string($nodeCountInput) || !is_numeric($nodeCountInput)) {
            $io->error('Node count must be a valid number.');
            return Command::INVALID;
        }
        $nodeCount = (int)$nodeCountInput;

        $connectivityInput = $input->getOption('connectivity');
        if (!is_string($connectivityInput) || !is_numeric($connectivityInput)) {
            $io->error('Connectivity factor must be a valid number.');
            return Command::INVALID;
        }
        $connectivity = (float)$connectivityInput;

        $outputFileInput = $input->getOption('output');
        if (!is_string($outputFileInput)) {
            $io->error('Output file must be a string.');
            return Command::INVALID;
        }
        $outputFile = $outputFileInput;

        $hierarchical = $input->getOption('hierarchical') === true;

        $depthInput = $input->getOption('depth');
        if (!is_string($depthInput) || !is_numeric($depthInput)) {
            $io->error('Depth must be a valid number.');
            return Command::INVALID;
        }
        $depth = (int)$depthInput;

        $widthInput = $input->getOption('width');
        if (!is_string($widthInput) || !is_numeric($widthInput)) {
            $io->error('Width must be a valid number.');
            return Command::INVALID;
        }
        $width = (int)$widthInput;

        $io->title('DePhpViz - Graph Creation Prototype');

        try {
            // Set up services
            $logger = new Logger('prototype');
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Info));

            $filesystem = new Filesystem();
            $graphBuilder = new GraphBuilder($logger);
            $graphSerializer = new GraphSerializer($filesystem, $logger);
            $graphTester = new GraphTester($graphBuilder, $graphSerializer);
            $sampleDataGenerator = new SampleDataGenerator();

            // Generate sample data
            $io->section('Generating sample data');

            if ($hierarchical) {
                $io->text(sprintf(
                    'Creating a hierarchical graph with depth %d and width %d',
                    $depth,
                    $width
                ));

                $sampleData = $sampleDataGenerator->generateClassHierarchy($depth, $width);
            } else {
                $io->text(sprintf(
                    'Creating a network graph with %d nodes and %.2f connectivity factor',
                    $nodeCount,
                    $connectivity
                ));

                $sampleData = $sampleDataGenerator->generateComplexNetwork($nodeCount, $connectivity);
            }

            $io->success(sprintf('Generated %d classes with dependencies', count($sampleData)));

            // Create a graph with the sample data
            $io->section('Building graph');
            $graph = $graphBuilder->buildGraph($sampleData);

            $nodeCount = count($graph->getNodes());
            $edgeCount = count($graph->getEdges());

            $io->text([
                sprintf('Graph contains %d nodes and %d edges', $nodeCount, $edgeCount),
                sprintf('Edge-to-node ratio: %.2f', $nodeCount > 0 ? $edgeCount / $nodeCount : 0)
            ]);

            // Validate the graph
            $io->section('Validating graph');
            $graphTester->validateGraph($graph, $io);

            // Serialize the graph
            $io->section('Serializing graph');
            $success = $graphSerializer->serializeToJson($graph, $outputFile);

            if ($success) {
                $io->success(sprintf('Graph data saved to %s', $outputFile));
                $io->text('You can visualize this graph by running the web server and opening the visualization page');
            } else {
                $io->error('Failed to serialize graph data');
                return Command::FAILURE;
            }

            return Command::SUCCESS;
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
