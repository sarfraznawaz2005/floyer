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
    public function setIO(SymfonyStyle $io);

    /**
     * Initialize.
     * @param ConnectorInterface $connector
     * @param array $options
     */
    public function init(ConnectorInterface $connector, array $options);

    /**
     * Starts deployment process
     */
    public function processDeployment();

    /**
     * Synchronize last local commit id with remote revision file.
     */
    public function sync();

    /**
     * Rollback previous deployment.
     */
    public function rollback();

    /**
     * List files deployed in previous deployment.
     */
    public function history();

    /**
     * Gets last local commit ID.
     */
    public function lastCommitIdLocal();

    /**
     * Gets last remoate/revision file commit ID.
     */
    public function lastCommitIdRemote();

    /**
     * Lists file to upload.
     */
    public function filesToUpload();

    /**
     * Creates zip file of files to upload.
     */
    public function createZipOfChangedFiles();

    public function checkDirty();
}