<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/17/2017
 * Time: 11:56 PM
 */

namespace Sarfraznawaz2005\Floyer\Traits;

trait Options
{
    protected $repo = '';
    protected $iniFileName = 'floyer.ini';

    public function getOptions()
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

        $iniFile = $this->repo . DIRECTORY_SEPARATOR . $this->iniFileName;

        $options = $this->parseIniFile($iniFile);

        if (is_array($options)) {
            $options = array_merge($defaults, $options['options']);
        }

        // defaults
        $options['passive'] = ($options['passive'] ?: true);
        $options['ssl'] = ($options['ssl'] ?: false);
        $options['port'] = ($options['port'] ?: 21);

        $this->validateOptions($options);

        $options['root'] = $this->addSlashIfMissing($options['root']);
        $options['domain'] = $this->addSlashIfMissing($options['domain']);
        $options['public_path'] = $this->addSlashIfMissing($options['public_path']);

        $options['exclude'][] = basename($iniFile);

        return $options;
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

    /**
     * Validates important options
     *
     * @param array $options
     * @throws \Exception
     */
    protected function validateOptions(array $options)
    {
        if (!trim($options['revision_file_name'])) {
            throw new \Exception('"revision_file_name" option not specified in ini file!');
        }

        if (!trim($options['driver'])) {
            throw new \Exception('"driver" option not specified in ini file!');
        }

        if (!trim($options['connector'])) {
            throw new \Exception('"connector" option not specified in ini file!');
        }

        if (!trim($options['root'])) {
            throw new \Exception('"root" option not specified in ini file!');
        }

        if (!trim($options['public_path'])) {
            throw new \Exception('"public_path" option not specified in ini file!');
        }

        if (!trim($options['domain'])) {
            throw new \Exception('"domain" option not specified in ini file!');
        }

        if (!filter_var($options['domain'], FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid value for "domain" option!');
        }

    }
}