<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/15/2017
 * Time: 6:05 PM
 */

namespace Sarfraznawaz2005\Floyer\Commands;

use Sarfraznawaz2005\Floyer\Contracts\DriverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Deploy extends Command
{
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

    // vars
    protected $driver = null;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;

        parent::__construct();
    }

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
        $this->driver->setIO(new SymfonyStyle($input, $output));
        $this->driver->init();

        $isSync = $input->getOption(static::SYNC);
        $isRollback = $input->getOption(static::ROLLBACK);
        $isHistory = $input->getOption(static::HISTORY);

        if ($isSync) {
            $this->driver->sync();
        } elseif ($isRollback) {
            $this->driver->rollback();
        } elseif ($isHistory) {
            $this->driver->history();
        } else {
            $this->driver->processDeployment();
        }
    }
}