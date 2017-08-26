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

        if ($this->confirm('Do you want to proceed with deployment?')) {
            $this->successBG('Deployment Started');

            $this->uploadDeployFiles();
        } else {
            $this->warning('Deployment Skipped!');
        }
    }

    /**
     * Synchronize last local commit id with remote revision file.
     */
    function sync()
    {
        $this->success('Sync revision ID started...');

        // update .rev file with new commit id
        $uploadStatus = $this->connector->write($this->revFile, $this->lastCommitId);

        if (!$uploadStatus) {
            $this->error('Could not update revision file.');
            exit;
        }

        $this->success('Sync revision ID completed!');
    }

    /**
     * Rollback previous deployment.
     */
    function rollback()
    {
        $this->history();

        @unlink($this->zipFile);
        @unlink($this->extractScriptFile);

        if ($this->confirm('Do you want to proceed with rollback?')) {
            $this->successBG('Rollback Started');
            $this->success('Current Remote Revision: ' . $this->lastCommitIdRemote);

            $this->uploadDeployFiles(true);
        } else {
            $this->warning('Rollback Skipped!');
        }
    }

    /**
     * List files deployed in previous deployment.
     */
    function history()
    {
        $this->title('Getting list of changed files in previous deployment:');

        $this->lastCommitIdRemote = $remoteCommitId = $this->lastCommitIdRemote();

        if (!trim($remoteCommitId)) {
            $this->error('No remote revision found.');
            exit;
        }

        $command = 'git diff-tree --no-commit-id --name-status -r ' . $remoteCommitId;

        $output = $this->exec($command);

        $files = explode("\n", $output);

        $this->gatherFiles($files, true);
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
            $this->error('No local revision found.');
            exit;
        }

        if (!trim($remoteCommitId)) {
            $this->error('No remote revision found.');
            exit;
        }

        // if local and remoate commit ids are same, nothing to upload
        if ($localCommitId === $remoteCommitId) {
            $this->success("No files to process!");
            exit;
        }

        $command = 'git diff --name-status ' . $remoteCommitId . ' ' . $localCommitId;

        $output = $this->exec($command);

        $files = explode("\n", $output);

        $this->gatherFiles($files, false);
    }

    /**
     * Creates zip file of files to upload.
     * @param bool $isRollback
     */
    function createZipOfChangedFiles($isRollback = false)
    {
        $target = 'HEAD';
        $zipName = $this->zipFile;

        if ($isRollback) {
            // get revision ID before previous deployment revision id
            $remoteCommitId = $this->lastCommitIdRemote;
            $command = "git log --format=%H -n2 $remoteCommitId";
            $output = explode("\n", $this->exec($command));

            if (isset($output[1])) {
                $target = $output[1];
            } else {
                $this->error('Could not find commit hash to rollback');
                exit;
            }
        }

        $command = "git archive --output=$zipName $target " . implode(' ', $this->filesChanged);

        $this->exec($command);
    }

    function checkDirty()
    {
        $gitStatus = $this->exec('git status --porcelain');

        if (trim($gitStatus)) {
            $this->warning('Stash your modifications before deploying.');
            exit;
        }
    }

    protected function deleteFiles()
    {
        foreach ($this->filesToDelete as $file) {
            $deleteStatus = $this->connector->deleteAt($file);

            if ($deleteStatus === true) {
                $this->success('Deleted: ' . $file);
            } else {
                $this->error("Could not delete '$file'");
            }
        }
    }

    /**
     * @param $files
     * @param $isRollback
     */
    protected function gatherFiles($files, $isRollback)
    {
        if (!is_array($files)) {
            $this->success("No files to process!");
            exit;
        }

        foreach ($files as $file) {

            if (!trim($file)) {
                continue;
            }

            if (strpos($file, 'warning: CRLF will be replaced by LF in') !== false) {
                continue;
            } elseif (strpos($file, 'original line endings in your working directory.') !== false) {
                continue;
            }

            $file = str_replace("\t", " ", $file);

            $array = explode(" ", $file);
            // remove empty items
            $array = array_filter($array);
            $array = array_map('trim', $array);

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

            $type = current($array);
            $path = next($array);

            if (!trim($path) || $path == '.' || $path == '..') {
                continue;
            }

            if ($type && $path) {
                if ($type === 'A' || $type === 'C' || $type === 'M' || $type === 'T') {
                    $this->filesChanged[] = $path;
                } elseif ($type === 'D') {
                    $this->filesToDelete[] = $path;
                }
            }
        }

        $this->filesChanged = $this->filterIgnoredFiles($this->filesChanged);

        if ($this->filesChanged) {

            if ($isRollback) {
                $this->success('Following files were uploaded in previous deployment:');
            } else {
                $this->success('Following files will be uploaded:');
            }

            $this->listing($this->filesChanged);
        }

        $this->filesToDelete = $this->filterIgnoredFiles($this->filesToDelete);

        if ($this->filesToDelete) {
            if ($isRollback) {
                $this->error('Following files were deleted in previous deployment:');
            } else {
                $this->error('Following files will be deleted:');
            }

            $this->listing($this->filesToDelete);
        }

        if (!$this->filesChanged && !$this->filesToDelete) {
            $this->success("No files to process!");
            exit;
        }
    }

    /**
     * @param bool $isRollback
     * @return mixed
     */
    protected function uploadDeployFiles($isRollback = false)
    {
        $type = $isRollback ? 'Rollback' : 'Deployment';

        if (!$this->filesChanged) {
            if ($this->filesToDelete) {
                $this->deleteFiles();
                $this->successBG($type . " Finished");
            } else {
                $this->success("No files to process!");
            }
            exit;
        }

        // create zip
        $this->success('Creating archive of files to upload...');
        $this->createZipOfChangedFiles($isRollback);

        // check if zip exists locally
        if (!file_exists($this->dir . $this->zipFile)) {

            // most likely there were too many files so command became too long
            // and archive file could not be created. Here we use alternative method
            // using sh.exe.
            // Ref: http://tinyurl.com/yb5nxna7

            $lastCommitId = $this->lastCommitId;
            $lastCommitIdRemote = $this->lastCommitIdRemote;
            $zipFile = $this->zipFile;

            $command = "sh -c 'git archive -o $zipFile HEAD $(git diff --name-only $lastCommitIdRemote $lastCommitId)'";
            $this->exec($command);
        }

        if (!file_exists($this->dir . $this->zipFile)) {
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

        $uploadStatus = $this->connector->upload($this->zipFile, '/');

        if (!$uploadStatus) {
            $this->error('Could not upload archive file.');
            exit;
        }

        $this->success('Extracting files on server...');

        $response = file_get_contents($this->options['domain'] . $this->options['public_path'] . $this->extractScriptFile);

        if ($response === 'ok') {

            $this->success('Files uploaded successfully...');

            // delete script file
            $this->connector->deleteAt($this->options['public_path'] . $this->extractScriptFile);

            if ($this->filesToDelete) {
                $this->deleteFiles();
            }

            $this->success('Finishing, please wait...');

            // delete deployment file
            $this->connector->delete($this->zipFile);

            // update .rev file with new commit id
            if (!$isRollback) {
                $uploadStatus = $this->connector->write($this->revFile, $this->lastCommitId);

                if (!$uploadStatus) {
                    $this->error('Could not update revision file.');
                    exit;
                }
            }

            $this->successBG($type . " Finished");
        } else {
            $this->error('Error: Unable to extract files.');
        }

        @unlink($this->zipFile);
        @unlink($this->extractScriptFile);
    }
}