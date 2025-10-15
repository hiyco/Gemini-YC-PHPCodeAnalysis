<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Main CLI Application
 */

namespace YcPca\Cli;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main CLI Application for PHP Code Analysis
 * 
 * Features:
 * - Multiple analysis commands
 * - Configuration management
 * - Error handling and logging
 * - Version management
 */
class Application extends BaseApplication
{
    private const APP_NAME = 'YC PHP Code Analyzer';
    private const APP_VERSION = '1.0.0-dev';
    
    public function __construct()
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);
        
        $this->setDefaultCommand('analyze');
        $this->addCommands($this->getDefaultCommands());
        $this->configureApplication();
    }

    /**
     * Get default commands
     */
    public function getDefaultCommands(): array
    {
        return [
            new HelpCommand(),
            new ListCommand(),
            new AnalyzeCommand(),
            new InitCommand(),
            new ValidateConfigCommand(),
            new SelfUpdateCommand()
        ];
    }

    /**
     * Configure application settings
     */
    private function configureApplication(): void
    {
        // Set application help
        $this->setHelp($this->getApplicationHelp());
        
        // Configure auto-exit behavior
        $this->setAutoExit(false);
        
        // Set catch exceptions
        $this->setCatchExceptions(true);
    }

    /**
     * Override doRun to add custom error handling
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        // Set up error handling
        $this->configureErrorHandling($output);
        
        // Display banner if not in quiet mode
        if (!$output->isQuiet()) {
            $this->displayBanner($output);
        }
        
        try {
            return parent::doRun($input, $output);
        } catch (\Throwable $e) {
            $this->renderException($e, $output);
            return 1;
        }
    }

    /**
     * Configure error handling
     */
    private function configureErrorHandling(OutputInterface $output): void
    {
        // Set memory limit
        ini_set('memory_limit', '1G');
        
        // Configure error reporting
        error_reporting(E_ALL);
        
        // Set custom error handler
        set_error_handler(function ($severity, $message, $file, $line) use ($output) {
            if ($output->isVerbose()) {
                $output->writeln(sprintf(
                    '<error>[PHP Error] %s in %s:%d</error>',
                    $message,
                    $file,
                    $line
                ));
            }
            
            return false; // Let PHP handle the error normally
        });
        
        // Set custom exception handler
        set_exception_handler(function (\Throwable $e) use ($output) {
            $this->renderException($e, $output);
        });
    }

    /**
     * Display application banner
     */
    private function displayBanner(OutputInterface $output): void
    {
        $banner = sprintf(
            "\n<info>%s</info> <comment>v%s</comment>\n" .
            "<comment>Advanced PHP Code Analysis Tool</comment>\n",
            self::APP_NAME,
            self::APP_VERSION
        );
        
        $output->write($banner);
    }

    /**
     * Render exception with appropriate formatting
     */
    private function renderException(\Throwable $e, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<error>Error: ' . $e->getMessage() . '</error>',
        ]);
        
        if ($output->isVerbose()) {
            $output->writeln([
                '<comment>Exception:</comment> ' . get_class($e),
                '<comment>File:</comment> ' . $e->getFile() . ':' . $e->getLine(),
            ]);
        }
        
        if ($output->isVeryVerbose()) {
            $output->writeln([
                '<comment>Stack trace:</comment>',
                $e->getTraceAsString(),
            ]);
        }
        
        $output->writeln('');
    }

    /**
     * Get application help text
     */
    private function getApplicationHelp(): string
    {
        return <<<'HELP'
<info>YC PHP Code Analyzer</info>

A comprehensive PHP code analysis tool that helps identify:
- Syntax errors and code quality issues
- Security vulnerabilities (OWASP Top 10)
- Performance bottlenecks
- Best practice violations

<comment>Quick Start:</comment>
  php bin/pca analyze src/              # Analyze src directory
  php bin/pca analyze --help            # Get detailed help

<comment>Configuration:</comment>
  php bin/pca init                      # Create configuration file
  php bin/pca validate-config           # Validate configuration

<comment>Advanced Usage:</comment>
  php bin/pca analyze src/ -f json -o report.json    # JSON report
  php bin/pca analyze src/ --include-security         # Security focus
  php bin/pca analyze src/ -s high --parallel         # High severity, parallel

For more information, visit: https://github.com/yc/php-code-analyzer
HELP;
    }
}

/**
 * Initialize configuration command
 */
class InitCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'init';
    protected static $defaultDescription = 'Initialize configuration file';

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        
        $configPath = getcwd() . '/.pca.json';
        
        if (file_exists($configPath)) {
            if (!$io->confirm('Configuration file already exists. Overwrite?', false)) {
                return self::SUCCESS;
            }
        }
        
        $config = [
            "analyzers" => [
                "syntax" => [
                    "enabled" => true,
                    "strict_types" => true,
                    "line_length" => 120
                ],
                "security" => [
                    "enabled" => true,
                    "owasp_top_10" => true,
                    "strict_mode" => false
                ],
                "performance" => [
                    "enabled" => false,
                    "memory_limit_check" => true
                ]
            ],
            "exclude_patterns" => [
                "*/vendor/*",
                "*/tests/*",
                "*/cache/*"
            ],
            "severity_threshold" => "medium",
            "parallel_processing" => true,
            "output_format" => "console"
        ];
        
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        
        $io->success('Configuration file created: ' . $configPath);
        $io->note('Edit the configuration file to customize analysis settings.');
        
        return self::SUCCESS;
    }
}

/**
 * Validate configuration command
 */
class ValidateConfigCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'validate-config';
    protected static $defaultDescription = 'Validate configuration file';

    protected function configure(): void
    {
        $this->addOption('config', 'c', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Configuration file path', '.pca.json');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        
        $configPath = $input->getOption('config');
        
        if (!file_exists($configPath)) {
            $io->error("Configuration file not found: {$configPath}");
            return self::FAILURE;
        }
        
        try {
            $config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
            
            $errors = $this->validateConfiguration($config);
            
            if (empty($errors)) {
                $io->success('Configuration file is valid.');
                return self::SUCCESS;
            } else {
                $io->error('Configuration validation failed:');
                foreach ($errors as $error) {
                    $io->writeln("  • {$error}");
                }
                return self::FAILURE;
            }
            
        } catch (\JsonException $e) {
            $io->error("Invalid JSON in configuration file: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function validateConfiguration(array $config): array
    {
        $errors = [];
        
        // Validate required sections
        if (!isset($config['analyzers'])) {
            $errors[] = 'Missing required section: analyzers';
        }
        
        // Validate severity threshold
        if (isset($config['severity_threshold'])) {
            $validSeverities = ['info', 'low', 'medium', 'high', 'critical'];
            if (!in_array($config['severity_threshold'], $validSeverities, true)) {
                $errors[] = 'Invalid severity_threshold. Must be one of: ' . implode(', ', $validSeverities);
            }
        }
        
        // Validate exclude patterns
        if (isset($config['exclude_patterns']) && !is_array($config['exclude_patterns'])) {
            $errors[] = 'exclude_patterns must be an array';
        }
        
        return $errors;
    }
}

/**
 * Self-update command
 */
class SelfUpdateCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'self-update';
    protected static $defaultDescription = 'Update to the latest version';

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        
        $io->note('Self-update functionality not implemented in development version.');
        $io->writeln('To update, pull the latest changes from the repository and run composer update.');
        
        return self::SUCCESS;
    }
}