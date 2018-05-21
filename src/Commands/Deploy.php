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
use Symfony\Component\Console\Input\InputArgument;
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
    const CONFIG_FILE = 'config';
    const SYNC = 'sync';
    const ROLLBACK = 'rollback';
    const HISTORY = 'history';

    // required arguments
    protected $arguments = [
        self::CONFIG_FILE => 'Config file to use.',
    ];

    // our options
    protected $options = [
        self::SYNC => 'Synchronize last local revision id with remote revision file.',
        self::ROLLBACK => 'Rollback previous deployment.',
        self::HISTORY => 'List files deployed in previous deployment.',
    ];

    // vars
    protected $currentDirectory = '';

    /**
     * Configure Command
     */
    protected function configure()
    {
        $this->setName($this->commandName);
        $this->setDescription($this->commandDescription);

        // attach arguments
        foreach ($this->arguments as $name => $description) {
            $this->addArgument($name, InputArgument::OPTIONAL, $description);
        }

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

        $this->currentDirectory = getcwd();
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
        $configFile = '';

        $io = new SymfonyStyle($input, $output);

        if (!trim($input->getArgument(static::CONFIG_FILE))) {
            $defaultServer = $this->currentDirectory . DIRECTORY_SEPARATOR . 'floyer_default_server.txt';

            if (file_exists($defaultServer)) {
                $defaultConfigFile = file_get_contents($defaultServer);

                if (trim($defaultConfigFile)) {
                    $configFile = $this->currentDirectory . DIRECTORY_SEPARATOR . trim($defaultConfigFile);
                }
            }

        } else {
            $configFile = $this->currentDirectory . DIRECTORY_SEPARATOR . $input->getArgument(static::CONFIG_FILE);
        }

        if (!file_exists($configFile)) {
            $io->writeln("<fg=red>'$configFile' does not exist!</>");
            exit;
        }

        $this->floyer($output);

        $this->iniFile = $configFile;

        $options = $this->getOptions();

        $server = basename($configFile);
        $io->note("Server Used: $server");

        // check to make sure we are good to go
        $this->checkUp($options, $io);

        try {

            $driver = 'Sarfraznawaz2005\Floyer\Drivers\\' . $options['driver'];
            $connector = 'Sarfraznawaz2005\Floyer\Connectors\\' . $options['connector'];

            $driver = new $driver;
            $connector = new $connector;

            $connector->connect($options);
            $driver->setIO($io);
            $driver->init($connector, $options);

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

        } catch (\Exception $e) {
            $io->error($e->getMessage());
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

    /**
     * Checks if everything is okay before we proceed
     *
     * @param $options
     * @param $io
     */
    protected function checkUp($options, $io)
    {
        if (!isset($options['driver'])) {
            $io->writeln("<fg=red>Driver is not specified in config file!</>");
            exit;
        }

        if (!isset($options['connector'])) {
            $io->writeln("<fg=red>Connector is not specified in config file!</>");
            exit;
        }

        if ($options['driver'] === 'Git') {
            if (!file_exists("{$this->currentDirectory}/.git")) {
                $io->writeln("<fg=red>'{$this->currentDirectory}' is not a Git repository.</>");
                exit;
            }
        } elseif ($options['driver'] === 'Svn') {
            if (!file_exists("{$this->currentDirectory}/.svn")) {
                $io->writeln("<fg=red>'{$this->currentDirectory}' is not a SVN repository.</>");
                exit;
            }
        }
    }
}