<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 5:20 PM
 */

namespace Sarfraznawaz2005\Floyer\Traits;

trait IO
{
    protected function line($text)
    {
        $this->io->writeln("<fg=white>$text</>");
    }

    protected function text($text)
    {
        $this->io->text($text);
    }

    protected function title($text)
    {
        $this->io->title($text);
    }

    protected function success($text)
    {
        $this->io->writeln("<fg=green>$text</>");
    }

    protected function successBG($text)
    {
        $this->io->writeln('<fg=black;bg=green>' . $text . '</>');
    }

    protected function warning($text)
    {
        $this->io->writeln("<fg=yellow>$text</>");
    }

    protected function error($text)
    {
        $this->io->writeln("<fg=red>$text</>");
    }

    protected function errorBG($text)
    {
        $this->io->error($text);
    }

    protected function listing(array $array)
    {
        $this->io->listing($array);
    }

    protected function table(array $array)
    {
        $this->io->table($array);
    }

    protected function newLine($text)
    {
        $this->io->newLine($text);
    }

    protected function note($text)
    {
        $this->io->note($text);
    }

    protected function caution($text)
    {
        $this->io->caution($text);
    }

    protected function ask($text)
    {
        return $this->io->ask($text);
    }

    protected function confirm($text)
    {
        return $this->io->confirm($text);
    }
}