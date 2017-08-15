<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/15/2017
 * Time: 6:05 PM
 */

namespace Sarfraznawaz2005\Floyer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected function configure()
    {
        $this->setName($this->commandName);
        $this->setDescription($this->commandDescription);

        // attach options
        foreach ($this->options as $name => $description) {
            $this->addOption($name, null, InputOption::VALUE_NONE, $description);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isSync = $input->getOption($this->options[static::SYNC]);
        $isRollback = $input->getOption($this->options[static::ROLLBACK]);
        $isHistory = $input->getOption($this->options[static::HISTORY]);

        if ($isSync) {
            $this->sync();
        } elseif ($isRollback) {
            $this->rollback();
        } elseif ($isHistory) {
            $this->history();
        } else {
            $this->processDeployment();
        }

        $output->writeln('xxxx');
    }

    protected function processDeployment()
    {

    }

    protected function sync()
    {

    }

    protected function rollback()
    {

    }

    protected function history()
    {

    }

    protected function dd($var)
    {
        var_dump($var);
        die();
    }
}