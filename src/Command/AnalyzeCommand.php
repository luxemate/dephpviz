<?php

declare(strict_types=1);

namespace DePhpViz\Command;

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
        $directory = $input->getArgument('directory');
        $outputFile = $input->getOption('output');

        $io->title('DePhpViz - PHP Dependency Analyzer');
        $io->section('Analyzing directory: ' . $directory);

        // Implementation will be added in future steps

        $io->success('Analysis completed. Output saved to: ' . $outputFile);

        return Command::SUCCESS;
    }
}
