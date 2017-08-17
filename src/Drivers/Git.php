<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:21 PM
 */

namespace Sarfraznawaz2005\Floyer\Drivers;

use Sarfraznawaz2005\Floyer\Contracts\DriverInterface;
use Sarfraznawaz2005\Floyer\Traits\IO;
use Symfony\Component\Console\Style\SymfonyStyle;

class Git implements DriverInterface
{
    use IO;

    protected $lastCommitId = '';
    protected $lastCommitIdRemote = '';
    protected $revFile = '.rev_floyer';
    protected $zipFile = 'deployment_floyer.zip';

    // console-related
    public $io = null;

    function init()
    {
        $this->lastCommitId = $this->lastCommitIdLocal();
    }

    /**
     * Connect to FTP/SFTP/etc
     */
    function connect()
    {

    }

    /**
     * Starts deployment process
     */
    function processDeployment()
    {
        $this->success('Getting list of changed files...');

        @unlink($this->zipFile);

        $this->line($this->filesToUpload());
    }

    /**
     * Synchronize last local commit id with remote revision file.
     */
    function sync()
    {
        $this->warning('sync');
        $this->success('Sync commit ID started...');

        // update .rev file with new commit id
        $uploadStatus = false;

        if (!$uploadStatus) {
            $this->error('Count not update revision file.');
            exit;
        }

        $this->success('Sync commit ID completed!');
    }

    /**
     * Rollback previous deployment.
     */
    function rollback()
    {
        $this->error('rollback');
    }

    /**
     * List files deployed in previous deployment.
     */
    function history()
    {
        $this->text('history');
    }

    /**
     * Sets up Synfony input and output
     * @param SymfonyStyle $io
     * @return null|void
     */
    function setIO(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    /**
     * Gets last local commit ID.
     */
    function lastCommitIdLocal()
    {
        return $this->runCommand('git rev-parse HEAD');
    }

    /**
     * Gets last remoate/revision file commit ID.
     */
    function lastCommitIdRemote()
    {
        // TODO: Implement lastCommitIdRemote() method.
    }

    /**
     * Lists file to upload.
     */
    function filesToUpload()
    {
        $localCommitId = $this->lastCommitId;
        $this->lastCommitIdRemote = $remoteCommitId = $this->lastCommitIdRemote();

        /*
        if (!trim($localCommitId)) {
            $this->error('No local commit id found.');
            exit;
        }

        if (!trim($remoteCommitId)) {
            $this->error('No remote commit id found.');
            exit;
        }

        // if local and remoate commit ids are same, nothing to upload
        if ($localCommitId === $remoteCommitId) {
            $this->text("No files to upload!");
            exit;
        }
        */

        $command = "git diff -r --no-commit-id --name-only --diff-filter=ACMRT $remoteCommitId $localCommitId";

        return $this->runCommand($command);
    }

    /**
     * Creates zip file of files to upload.
     */
    function createZipOfChangedFiles()
    {
        // TODO: Implement createZipOfChangedFiles() method.
    }

    protected function runCommand($command)
    {
        return shell_exec($command . ' 2>&1');
    }
}