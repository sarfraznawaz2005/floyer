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
    function connect();

    function upload($path, $destination, $overwrite = true);

    function exists($path);

    function existsAt($path);

    function delete($path);

    function deleteAt($path);

    function write($path, $contents, $overwrite = true);

    function read($path);
}