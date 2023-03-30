<?php

/**
 * @file
 */

namespace Drush\Commands\drush_stream_output;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Commands\DrushCommands;
use Drush\Log\SuccessInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * A Drush commandfile.
 */
class StreamOutputCommands extends DrushCommands {
  /**
   * Alter the logger based on the options.
   *
   * @hook init *
   */
  public function customLogger(ArgvInput $argv, AnnotationData $annotationData): void {
    // @todo: add an option to check the drush.yml ($this->config->get('key'))
    // so it not needed to do at command execution time only.

    $logger_option = $argv->getOption('logger');

    $loggers = explode(',', $logger_option);
    $loggers = array_filter($loggers, function ($logger) {
      return in_array($logger, ['stdout', 'file']);
    });

    $log_file_path = $argv->getOption('log-file-path');

    // Display a warning if the --log-file-path option is being used without
    // the file logger being listed in the --logger option.
    if (!in_array('file', $loggers) && !empty($log_file_path)) {
      $this->logger()->warning('The --log-file-path is ignored as the file logger is not listed in the --logger option.');
    }

    // If the loggers is or contain the file logger but no path is provided,
    // remove it from the logger list.
    if (in_array('file', $loggers) && empty($log_file_path)) {
      $this->logger()->warning('The --log-file-path option is mandatory when the file logger is used. Falling back to the default logger.');
      $loggers = array_filter($loggers, function ($logger) {
        return $logger !== 'file';
      });
    }

    // Exit early if the logger is not properly overridden.
    if (empty($loggers) || (count($loggers) === 1 && in_array('stdout', $loggers))) {
      return;
    }

    $loggerManager = $this->logger();

    // If the default logger is not listed, reset the loggerManager so the
    // default one is removed.
    if (!in_array('stdout', $loggers)) {
      $loggerManager->reset();
    }

    if (in_array('file', $loggers)) {
      $verbosityLevelMap = [SuccessInterface::SUCCESS => OutputInterface::VERBOSITY_NORMAL];
      $formatLevelMap = [SuccessInterface::SUCCESS => LogLevel::INFO];

      $streamOutput = new StreamOutput(fopen($log_file_path, 'a', false));
      $streamLogger = new ConsoleLogger($streamOutput, $verbosityLevelMap, $formatLevelMap);
      $loggerManager->add('file', $streamLogger);
    }
  }

  /**
   * @hook option *
   */
  public function addLoggerOption(Command $command, AnnotationData $annotationData)
  {
    $command->addOption(
      'logger',
      '',
      InputOption::VALUE_REQUIRED,
      'The logger to use for the command (stdout, file). Separate by a comma if multiple loggers. Default is stdout.'
    );

    $command->addOption(
      'log-file-path',
      '',
      InputOption::VALUE_REQUIRED,
      'The path to the log file',
    );
  }
}
