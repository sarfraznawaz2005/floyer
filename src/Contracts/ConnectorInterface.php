<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:22 PM
 */

namespace Sarfraznawaz2005\Floyer\Contracts;

interface ConnectorInterface
{
    public function connect(array $options);

    public function upload($path, $destination, $overwrite = true);

    public function exists($path);

    public function delete($path);

    public function write($path, $contents, $overwrite = true);

    public function read($path);
}