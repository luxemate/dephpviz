<?php

declare(strict_types=1);

namespace DePhpViz\Util;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes logs to Symfony Console output.
 */
class ConsoleHandler extends AbstractProcessingHandler
{
    private OutputInterface $output;

    /**
     * @param OutputInterface $output The console output
     * @param Level $level The minimum logging level
     * @param bool $bubble Whether the messages that are handled can bubble up the stack
     */
    public function __construct(
        OutputInterface $output,
        Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->output = $output;
    }



    /**
     * {@inheritdoc}
     */
    protected function write(LogRecord $record): void
    {
        $verbosityLevel = $this->getVerbosityLevel($record->level);

        // Only write logs that match the console's verbosity setting
        if ($this->output->getVerbosity() >= $verbosityLevel) {
            $this->output->writeln($this->getFormattedMessage($record), $verbosityLevel);
        }
    }

    /**
     * Get the formatted log message for console output.
     */
    private function getFormattedMessage(LogRecord $record): string
    {
        $levelName = $record->level->getName();
        $message = $record->message;

        // Apply console formatting based on log level
        $style = match (true) {
            $record->level->value >= Level::Error->value => 'error',
            $record->level->value >= Level::Warning->value => 'comment',
            $record->level->value >= Level::Notice->value => 'info',
            default => 'fg=default'
        };

        return sprintf('<%s>[%s] %s</%s>', $style, $levelName, $message, $style);
    }

    /**
     * Map Monolog levels to Symfony Console verbosity levels.
     */
    private function getVerbosityLevel(Level $level): int
    {
        return match (true) {
            $level->value >= Level::Error->value => OutputInterface::VERBOSITY_NORMAL,
            $level->value >= Level::Warning->value => OutputInterface::VERBOSITY_NORMAL,
            $level->value >= Level::Notice->value => OutputInterface::VERBOSITY_VERBOSE,
            $level->value >= Level::Info->value => OutputInterface::VERBOSITY_VERY_VERBOSE,
            default => OutputInterface::VERBOSITY_DEBUG,
        };
    }
}
