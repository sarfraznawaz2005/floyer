<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:21 PM
 */

namespace Sarfraznawaz2005\Floyer\Connectors;

Abstract class Base
{
    protected $repo = '';
    protected $iniFileName = 'floyer.ini';

    public $options = [];

    protected $connector = null;

    public function init()
    {
        $this->repo = getcwd();

        // read ini file options
        $defaults = [
            'host' => '',
            'username' => '',
            'password' => '',
            'root' => '/',
            'port' => null,
            'passive' => null,
            'timeout' => null,
            'ssl' => false,
        ];

        if (!file_exists("$this->repo/.git")) {
            $this->error("'{$this->repo}' is not a Git repository.");
            exit;
        }

        $iniFile = $this->repo . DIRECTORY_SEPARATOR . $this->iniFileName;

        $options = $this->parseIniFile($iniFile);

        if (is_array($options)) {
            $options = array_merge($defaults, $options['options']);
        }

        // defaults
        $options['passive'] = ($options['passive'] ?: true);
        $options['ssl'] = ($options['ssl'] ?: false);
        $options['port'] = ($options['port'] ?: 21);

        $options['root'] = $this->addSlashIfMissing($options['root']);
        $options['domain'] = $this->addSlashIfMissing($options['domain']);
        $options['public_path'] = $this->addSlashIfMissing($options['public_path']);

        $this->options = $options;
    }

    protected function parseIniFile($iniFile)
    {
        if (!file_exists($iniFile)) {
            throw new \Exception("'$iniFile' does not exist.");
        } else {
            $values = parse_ini_file($iniFile, true);

            if (!$values) {
                throw new \Exception("'$iniFile' is not a valid .ini file.");
            } else {
                return $values;
            }
        }
    }

    private function addSlashIfMissing($path)
    {
        if (substr($path, -1, 1) !== '/') {
            $path = $path . '/';
        }

        return $path;
    }
}