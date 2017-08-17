<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:21 PM
 */

namespace Sarfraznawaz2005\Floyer\Drivers;

use Sarfraznawaz2005\Floyer\Contracts\DriverInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Git extends Base implements DriverInterface
{
    /**
     * Starts deployment process
     */
    function processDeployment()
    {
        $this->title('Getting list of changed files...');

        @unlink($this->zipFile);

        $this->line($this->filesToUpload());

        if ($this->confirm('Above files will be uploaded, do you wish to continue?')) {
            $this->successBG('Deployment Started');

            // create zip
            $this->success('Creating archive of files to upload...');
            $this->createZipOfChangedFiles();

            // check if zip exists locally
            if (!file_exists($this->zipFile)) {
                $this->error('Could not create archive file.');
                exit;
            }

            // upload zip
            $this->success('Uploading archive file...');

            $uploadStatus = $this->connector->upload($this->zipFile, $this->options['root']);

            if (!$uploadStatus) {
                $this->error('Could not upload archive file.');
                exit;
            }

            // delete script file if already there
            @$this->connector->deleteAt($this->options['public_path'] . $this->extractScriptFile);

            // upload extract zip script on server
            file_put_contents($this->extractScriptFile, $this->extractScript());
            $uploadStatus = $this->connector->upload($this->extractScriptFile, $this->options['public_path']);

            if (!$uploadStatus) {
                $this->error('Could not upload script file.');
                exit;
            }

            @unlink($this->extractScriptFile);

            $response = file_get_contents($this->options['domain'] . $this->options['public_path'] . $this->extractScriptFile);

            if ($response === 'ok') {
                // delete script file
                $this->connector->deleteAt($this->options['public_path'] . $this->extractScriptFile);

                $this->success('Deploying changed files...');

                // delete deployment file
                $this->connector->delete($this->zipFile);

                // update .rev file with new commit id
                $uploadStatus = $this->connector->write($this->revFile, $this->lastCommitId);

                if (!$uploadStatus) {
                    $this->error('Could not update revision file.');
                    exit;
                }

                $this->successBG('Deployment Finished');
            } else {
                $this->error('Unknown Error!');
            }

        } else {
            $this->warning('Deployment Skipped!');
        }

        @unlink($this->zipFile);
    }

    /**
     * Synchronize last local commit id with remote revision file.
     */
    function sync()
    {
        $this->warning('sync');
        $this->success('Sync commit ID started...');

        // update .rev file with new commit id
        $uploadStatus = $this->connector->write($this->revFile, $this->lastCommitId);

        if (!$uploadStatus) {
            $this->error('Could not update revision file.');
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
        return $this->exec('git rev-parse HEAD');
    }

    /**
     * Gets last remoate/revision file commit ID.
     */
    function lastCommitIdRemote()
    {
        $exists = $this->connector->exists($this->revFile);

        if (!$exists) {
            $this->connector->write($this->revFile, $this->lastCommitId);
        }

        $lastCommidId = $this->connector->read($this->revFile);

        return $lastCommidId;
    }

    /**
     * Lists file to upload.
     */
    function filesToUpload()
    {
        $localCommitId = $this->lastCommitId;
        $this->lastCommitIdRemote = $remoteCommitId = $this->lastCommitIdRemote();

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

        $command = "git diff -r --no-commit-id --name-only --diff-filter=ACMRT $remoteCommitId $localCommitId";
        //$this->line($command);

        return $this->exec($command);
    }

    /**
     * Creates zip file of files to upload.
     */
    function createZipOfChangedFiles()
    {
        $localCommitId = $this->lastCommitId;
        $remoteCommitId = $this->lastCommitIdRemote;
        $zipName = $this->zipFile;

        $contents = "git archive --output=$zipName HEAD $(git diff -r --no-commit-id --name-only --diff-filter=ACMRT $remoteCommitId $localCommitId)";

        file_put_contents('archive.sh', $contents);

        exec('archive.sh', $result);
        @unlink('archive.sh');
    }
}