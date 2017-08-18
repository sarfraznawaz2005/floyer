<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:22 PM
 */

namespace Sarfraznawaz2005\Floyer\Contracts;

use Symfony\Component\Console\Style\SymfonyStyle;

interface DriverInterface
{
    /**
     * Sets up Synfony input and output
     * @param SymfonyStyle $io
     * @return null
     */
    function setIO(SymfonyStyle $io);

    /**
     * Initialize.
     * @param ConnectorInterface $connector
     * @return
     */
    function init(ConnectorInterface $connector);

    /**
     * Starts deployment process
     */
    function processDeployment();

    /**
     * Synchronize last local commit id with remote revision file.
     */
    function sync();

    /**
     * Rollback previous deployment.
     */
    function rollback();

    /**
     * List files deployed in previous deployment.
     */
    function history();

    /**
     * Gets last local commit ID.
     */
    function lastCommitIdLocal();

    /**
     * Gets last remoate/revision file commit ID.
     */
    function lastCommitIdRemote();

    /**
     * Lists file to upload.
     */
    function filesToUpload();

    /**
     * Creates zip file of files to upload.
     */
    function createZipOfChangedFiles();

    function checkDirty();
}