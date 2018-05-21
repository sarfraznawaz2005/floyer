<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/17/2017
 * Time: 4:47 PM
 */

namespace Sarfraznawaz2005\Floyer\Connectors;

use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Filesystem;
use Sarfraznawaz2005\Floyer\Contracts\ConnectorInterface;

class FTP implements ConnectorInterface
{
    protected $connector = null;

    public function connect(array $options)
    {
        try {
            $this->connector = new Filesystem(new FtpAdapter($options));
        } catch (\Exception $e) {
            echo "\r\nError: {$e->getMessage()}\r\n";
            exit;
        }
    }

    public function upload($path, $destination, $overwrite = true)
    {
        $destination = $destination . '/' . basename($path);
        $destination = str_replace('//', '/', $destination);

        if ($overwrite && $this->existsAt($destination)) {
            $this->deleteAt($destination);
        }

        $stream = fopen($path, 'r+');
        $result = $this->connector->writeStream($destination, $stream);
        fclose($stream);

        return $result;
    }

    public function exists($path)
    {
        return $this->connector->has(basename($path));
    }

    public function existsAt($path)
    {
        return $this->connector->has($path);
    }

    public function delete($path)
    {
        $this->deleteAt(basename($path));
    }

    public function deleteAt($path)
    {
        try {
            if (!is_dir($path)) {
                return $this->connector->delete($path);
            } else {
                return $this->connector->deleteDir($path);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function write($path, $contents, $overwrite = true)
    {
        if ($overwrite && $this->exists($path)) {
            $this->delete($path);
        }

        return $this->connector->write(basename($path), $contents);
    }

    public function read($path)
    {
        return $this->connector->read(basename($path));
    }
}