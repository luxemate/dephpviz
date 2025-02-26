<?php

declare(strict_types=1);

namespace DePhpViz\Command;

use DePhpViz\Web\WebServer;
use DePhpViz\Web\WebServerConfig;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'server',
    description: 'Start the web server for the dependency visualization'
)]
class ServerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The server host', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The server port', '8080')
            ->addOption('graph-data', 'g', InputOption::VALUE_OPTIONAL, 'Path to the graph data file', 'var/graph.json')
            ->addOption('no-browser', null, InputOption::VALUE_NONE, 'Do not open the browser automatically')
            ->addOption('foreground', 'f', InputOption::VALUE_NONE, 'Run in foreground');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse options
        $host = $input->getArgument('host');
        if (!is_string($host)) {
            $io->error('Host argument must be a string.');
            return Command::INVALID;
        }

        $port = $input->getOption('port');
        if (!is_int($port)) {
            $io->error('Port option must be an int.');
            return Command::INVALID;
        }

        $graphData = $input->getOption('graph-data');
        if (!is_string($graphData)) {
            $io->error('Graph data option must be a string.');
            return Command::INVALID;
        }

        $noBrowser = $input->getOption('no-browser') === true;
        $foreground = $input->getOption('foreground') === true;

        $io->title('DePhpViz - Web Server');

        if (!file_exists($graphData)) {
            $io->error(sprintf('Graph data file not found: %s', $graphData));
            return Command::FAILURE;
        }

        // Create server config
        $config = new WebServerConfig(
            host: $host,
            port: $port,
            publicDir: 'public',
            graphDataFile: $graphData
        );

        // Create server
        $logger = new Logger('server');
        $logger->pushHandler(new StreamHandler('php://stdout', Level::Info));
        $server = new WebServer($config, $logger);

        // Start server
        $io->section('Starting web server');
        $io->text([
            sprintf('Host: %s', $host),
            sprintf('Port: %d', $port),
            sprintf('Graph data: %s', $graphData),
            sprintf('URL: %s', $config->getVisualizationUrl())
        ]);

        $process = $server->start(!$foreground);

        if (!$noBrowser) {
            $this->openBrowser($config->getVisualizationUrl(), $io);
        }

        if ($foreground) {
            $io->success('Server stopped');
            return Command::SUCCESS;
        } else {
            $io->success([
                'Server started in background',
                sprintf('PID: %d', $process?->getPid() ?? 0),
                'Press Ctrl+C to stop'
            ]);

            // Keep running until interrupted
            while ($process?->isRunning()) {
                sleep(1);
            }

            return Command::SUCCESS;
        }
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
