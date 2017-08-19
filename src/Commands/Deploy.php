<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/15/2017
 * Time: 6:05 PM
 */

namespace Sarfraznawaz2005\Floyer\Commands;

use Sarfraznawaz2005\Floyer\Traits\Options;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Deploy extends Command
{
    use Options;

    protected $commandName = 'deploy';
    protected $commandDescription = "Starts deployment process.";

    // constants
    const SYNC = 'sync';
    const ROLLBACK = 'rollback';
    const HISTORY = 'history';

    // our options
    protected $options = [
        self::SYNC => 'Synchronize last local commit id with remote revision file.',
        self::ROLLBACK => 'Rollback previous deployment.',
        self::HISTORY => 'List files deployed in previous deployment.',
    ];

    /**
     * Configure Command
     */
    protected function configure()
    {
        $this->setName($this->commandName);
        $this->setDescription($this->commandDescription);

        // attach options
        foreach ($this->options as $name => $description) {
            $this->addOption($name, null, InputOption::VALUE_NONE, $description);
        }

        $this->init();
    }

    /**
     * Initialization stuff.
     */
    protected function init()
    {
        set_time_limit(0);
    }

    /**
     * Execute console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->floyer($output);

        $currentDirectory = getcwd();

        if (!file_exists("$currentDirectory/.git")) {
            $this->io->writeln("<fg=red>'{$currentDirectory}' is not a Git repository.</>");
            exit;
        }

        $options = $this->getOptions();

        if (!isset($options['driver'])) {
            $this->io->writeln("<fg=red>Driver is not specified in config file!</>");
            exit;
        }

        if (!isset($options['connector'])) {
            $this->io->writeln("<fg=red>Connector is not specified in config file!</>");
            exit;
        }

        $driver = 'Sarfraznawaz2005\Floyer\Drivers\\' . $options['driver'];
        $connector = 'Sarfraznawaz2005\Floyer\Connectors\\' . $options['connector'];

        $driver = new $driver;
        $connector = new $connector;

        $connector->connect();
        $driver->setIO($io);
        $driver->init($connector);

        $isSync = $input->getOption(static::SYNC);
        $isRollback = $input->getOption(static::ROLLBACK);
        $isHistory = $input->getOption(static::HISTORY);

        if ($isSync) {
            $driver->sync();
        } elseif ($isRollback) {
            $driver->rollback();
        } elseif ($isHistory) {
            $driver->history();
        } else {
            $driver->processDeployment();
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function floyer(OutputInterface $output)
    {
        $output->writeln('<fg=black;bg=green>-------------------------------------------------</>');
        $output->writeln('<fg=black;bg=green>|                     Floyer                    |</>');
        $output->writeln('<fg=black;bg=green>-------------------------------------------------</>');
    }
}