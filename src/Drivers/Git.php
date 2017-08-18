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
        @unlink($this->extractScriptFile);

        $this->checkDirty();

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

            $this->success('Uploading extract files script...');

            // upload extract zip script on server
            file_put_contents($this->extractScriptFile, $this->extractScript());
            $uploadStatus = $this->connector->upload($this->extractScriptFile, $this->options['public_path']);

            if (!$uploadStatus) {
                $this->error('Could not upload script file.');
                exit;
            }

            $this->success('Uploading zip archive of files changed...');

            $uploadStatus = $this->connector->upload($this->zipFile, $this->options['root']);

            if (!$uploadStatus) {
                $this->error('Could not upload archive file.');
                exit;
            }


            $response = file_get_contents($this->options['domain'] . $this->options['public_path'] . $this->extractScriptFile);

            if ($response === 'ok') {
                // delete script file
                $this->connector->deleteAt($this->options['public_path'] . $this->extractScriptFile);

                $this->success('Deploying changed files...');

                if ($this->filesToDelete) {

                    foreach ($this->filesToDelete as $file) {
                        $deleteStatus = $this->connector->delete($file);

                        if ($deleteStatus === true) {
                            $this->success('Deleted: ' . $file);
                        } else {
                            $this->error("Could not delete '$file'. Reason: " . $deleteStatus);
                        }
                    }
                }

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
        @unlink($this->extractScriptFile);
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

        return $this->connector->read($this->revFile);
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
            $this->success("No files to upload!");
            exit;
        }

        /*
         * Git Status Codes
         *
         * A: addition of a file
         * C: copy of a file into a new one
         * D: deletion of a file
         * M: modification of the contents or mode of a file
         * R: renaming of a file
         * T: change in the type of the file
         * U: file is unmerged (you must complete the merge before it can be committed)
         * X: "unknown" change type (most probably a bug, please report it)
         */

        $command = 'git diff --name-status ' . $remoteCommitId . ' ' . $localCommitId;

        $output = $this->exec($command);

        $files = explode("\n", $output);

        foreach ($files as $file) {

            if (!$file) {
                continue;
            }

            if (strpos($file, 'warning: CRLF will be replaced by LF in') !== false) {
                continue;
            } elseif (strpos($file, 'original line endings in your working directory.') !== false) {
                continue;
            }

            $array = explode("\t", $file);
            $type = $array[0];
            $path = $array[1];

            if ($type === 'A' || $type === 'C' || $type === 'M' || $type === 'T') {
                $this->filesChanged[] = $path;
            } elseif ($type === 'D') {
                $this->filesToDelete[] = $path;
            }
        }

        if ($this->filesToDelete) {
            $this->success('Following files will be uploaded:');
            $this->listing($this->filesChanged);
        }

        if ($this->filesToDelete) {
            $this->error('Following files will be deleted:');

            foreach ($this->filesToDelete as $file) {
                $this->error('* ' . $file);
            }
        }
    }

    /**
     * Creates zip file of files to upload.
     */
    function createZipOfChangedFiles()
    {
        $zipName = $this->zipFile;

        $command = "git archive --output=$zipName HEAD " . implode(' ', $this->filesChanged);

        exec($command);
    }

    function checkDirty()
    {
        $gitStatus = $this->exec('git status --porcelain');

        if (trim($gitStatus)) {
            $this->warning('Stash your modifications before deploying.');
            exit;
        }
    }
}