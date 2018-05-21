<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/17/2017
 * Time: 4:47 PM
 */

namespace Sarfraznawaz2005\Floyer\Connectors;

use League\Flysystem\Sftp\SftpAdapter as SftpAdapter;
use League\Flysystem\Filesystem;

class SFTP extends FTP
{
    protected $connector = null;

    public function connect(array $options)
    {
        try {

            // see if key file exists
            if (!trim($options['key_file']) || !is_file($options['key_file'])) {
                throw new \Exception("Private key file: {$options['key_file']} doesn't exists.");
            }

            $options['privateKey'] = $options['key_file'];

            if (!trim($options['port']) || $options['port'] == 21) {
                $options['port'] = 22;
            }

            $this->connector = new Filesystem(new SftpAdapter($options));

        } catch (\Exception $e) {
            echo "\r\nError: {$e->getMessage()}\r\n";
            exit;
        }
    }
}