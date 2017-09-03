<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:21 PM
 */

namespace Sarfraznawaz2005\Floyer\Drivers;

use RecursiveIteratorIterator;
use Sarfraznawaz2005\Floyer\Contracts\DriverInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Svn extends Base implements DriverInterface
{
    protected $exportFolder = 'floyer_svn_export';

    /**
     * Starts deployment process
     */
    function processDeployment()
    {
        $this->title('Getting list of changed files...');

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
            $this->oops('Could not update revision file.');
        }

        $this->success('Sync revision ID completed!');
    }

    /**
     * Rollback previous deployment.
     */
    function rollback()
    {
        $this->history();

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

        $this->lastCommitIdRemote = $this->lastCommitIdRemote();

        if (!trim($this->lastCommitIdRemote)) {
            $this->oops('No remote revision found.');
        }

        $prevRevision = $this->lastCommitIdRemote - 1;
        $command = 'svn diff -r ' . $prevRevision . ':' . $this->lastCommitIdRemote . ' --summarize';

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
        $output = $this->exec('svn info -r HEAD');

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (false !== strpos($line, 'Revision:')) {
                $array = explode(":", $line);

                if (isset($array[1]) && is_numeric(trim($array[1]))) {
                    return trim($array[1]);
                }
            }
        }

        $this->oops('No local revision found.');
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
        $this->lastCommitIdRemote = $this->lastCommitIdRemote();

        if (!trim($this->lastCommitId)) {
            $this->oops('No local revision found.');
        }

        if (!trim($this->lastCommitIdRemote)) {
            $this->oops('No remote revision found.');
        }

        // if local and remoate commit ids are same, nothing to upload
        if ($this->lastCommitId === $this->lastCommitIdRemote) {
            $this->success("No files to process!");
            exit;
        }

        $command = 'svn diff -r ' . $this->lastCommitIdRemote . ':' . $this->lastCommitId . ' --summarize';

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
        $destinationFolder = $this->dir . $this->exportFolder;

        if (!$isRollback) {
            /*
        // we could not find a way to svn export between two versions via "svn export" command
        // so here we create batch script that will export files in a folder and from there
        // we will use PHP to add these files to zip file. This might not work on non-windows
        // systems.

            $localCommitId = $this->lastCommitId;

            $script = <<< SCRIPT
@echo off

FOR /F "tokens=1,2" %%I IN ('svn diff --summarize -r %1') DO (
    IF NOT %%I == D (
        IF NOT EXIST %2\%%J\.. mkdir %2\%%J\..

        svn export --depth empty -q --force %%J %2\%%J
        echo %2\%%J
    )
)
SCRIPT;

            file_put_contents($this->exportFolder . '.bat', $script);

            $command = "$this->exportFolder.bat {$this->lastCommitIdRemote}:$localCommitId $this->exportFolder";

            $this->exec($command);
            */

            // copy files manually to specified folder and then we will zip them //
            if (!file_exists($this->exportFolder)) {
                mkdir($this->exportFolder, 0777);
            }

            foreach ($this->filesChanged as $file) {
                $folder = $this->exportFolder . DIRECTORY_SEPARATOR . dirname($file);

                if (!file_exists($folder)) {
                    @mkdir($folder, 0777, true);
                }

                if (!file_exists($this->exportFolder . DIRECTORY_SEPARATOR . $file)) {
                    copy($file, $this->exportFolder . DIRECTORY_SEPARATOR . $file);
                }
            }

        } else {

            // since in svn revision numbers are in sequence so we just subtract 1
            // to get to one revision before current one.
            $this->lastCommitIdRemote -= 1;

            foreach ($this->filesChanged as $file) {
                $folder = $destinationFolder . DIRECTORY_SEPARATOR . dirname($file);

                @mkdir($folder, 0777, true);

                // svn export can dig a file out from past revision.
                $command = "svn export -r {$this->lastCommitIdRemote} --depth empty -q --force $file $folder";
                $this->exec($command);
            }
        }

        if (!file_exists($destinationFolder)) {
            $this->oops('Could not create archive file!');
        }

        // remove those files from export folder which are excluded
        $iterator = new RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destinationFolder,
                \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $filename => $fileInfo) {
            $pathArray = explode($this->exportFolder, $filename);
            $currentFile = trim($pathArray[1], '\\');

            if (in_array(trim($currentFile), $this->filesChanged)) {
                continue;
            }

            if (!$fileInfo->isDir()) {
                @unlink($filename);
            }
        }

        // now create zip file of these files
        $this->zipData($destinationFolder, $this->zipFile);

        //@unlink($this->exportFolder . '.bat');
        $this->recursiveRmDir($destinationFolder);
    }

    function checkDirty()
    {
        @unlink($this->zipFile);
        @unlink($this->extractScriptFile);
        //@unlink($this->exportFolder . '.bat');
        @$this->recursiveRmDir($this->dir . $this->exportFolder);

        $dirtyTypes = [
            'A',
            'A+',
            'M',
            'D',
        ];

        $status = $this->exec('svn status');

        if (trim($status)) {

            $files = explode("\n", $status);

            foreach ($files as $file) {
                $file = str_replace("\t", " ", $file);

                $array = explode(" ", $file);
                // remove empty items and padding whitespace
                $array = array_filter($array);
                $array = array_map('trim', $array);

                $type = current($array);

                if (in_array($type, $dirtyTypes)) {
                    $this->warning('Commit your modifications before deploying.');
                    exit;
                }
            }

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
             * SVN Status Codes
             *
             * A: addition of a file
             * A+: This file will be moved (after commit)
             * C: This file conflicts with the version in the repo
             * D: deletion of a file
             * I: Item is being ignored (e.g. with the svn:ignore property).
             * L: Item is locked
             * M: modification of the contents
             * R: This file got replaced
             * S: This signifies that the file or directory has been switched from the path of the rest of the working copy (using svn switch) to a branch
             * T: File was locked in this working copy, but the lock has been stolen and is invalid
             * U: file is unmerged (you must complete the merge before it can be committed)
             * X: Item is related to an externals definition.
             * ?: Item is not under version control.
             * !: Item is missing (e.g. you moved or deleted it without using svn).
             * ~: Item is versioned as one kind of object (file, directory, link), but has been replaced by different kind of object.
             * *: A newer revision of the item exists on the server.
             */

            $type = current($array);
            $path = next($array);

            if (!trim($path) || $path == '.' || $path == '..') {
                continue;
            }

            if ($type && $path) {
                if ($type === 'A' || $type === 'A+' || $type === 'M') {
                    $this->filesChanged[] = $path;
                } elseif ($type === 'D') {
                    $this->filesToDelete[] = $path;
                }
            }
        }

        // do not upload excluded files
        $this->filesChanged = $this->filterIgnoredFiles($this->filesChanged);

        if ($this->filesChanged) {
            if ($isRollback) {
                $this->success('Following files were uploaded in previous deployment:');
            } else {
                $this->success('Following files will be uploaded:');
            }

            $this->listing($this->filesChanged);
        }

        // do not delete excluded files
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

        if (!file_exists($this->dir . $this->zipFile)) {
            $this->oops('Could not create archive file.');
        }

        $this->success('Uploading extract files script...');

        // upload extract zip script on server
        file_put_contents($this->extractScriptFile, $this->extractScript());

        $uploadStatus = $this->connector->upload($this->extractScriptFile, $this->options['public_path']);

        if (!$uploadStatus) {
            $this->oops('Could not upload script file.');
        }

        $this->success('Uploading zip archive of files changed...');

        $uploadStatus = $this->connector->upload($this->zipFile, '/');

        if (!$uploadStatus) {
            $this->oops('Could not upload archive file.');
        }

        $this->success('Extracting files on server...');

        $response = file_get_contents($this->options['domain'] . $this->options['public_path'] . $this->extractScriptFile);

        if ($response === 'ok') {

            $this->success('Files uploaded successfully...');

            @unlink($this->zipFile);
            @unlink($this->extractScriptFile);
            //@unlink($this->exportFolder . '.bat');
            @$this->recursiveRmDir($this->dir . $this->exportFolder);

            if ($this->filesToDelete) {
                $this->deleteFiles();
            }

            $this->success('Finishing, please wait...');

            // delete script file
            $this->connector->deleteAt($this->options['public_path'] . $this->extractScriptFile);

            // delete deployment file
            $this->connector->delete($this->zipFile);

            // update .rev file with new commit id
            if (!$isRollback) {
                $uploadStatus = $this->connector->write($this->revFile, $this->lastCommitId);

                if (!$uploadStatus) {
                    $this->oops('Could not update revision file.');
                }
            }

            $this->successBG($type . " Finished");
        } else {
            $this->error('Error: Unable to extract files.');
        }

        @unlink($this->zipFile);
        @unlink($this->extractScriptFile);
        //@unlink($this->exportFolder . '.bat');
        @$this->recursiveRmDir($this->dir . $this->exportFolder);
    }

    protected function oops($message)
    {
        $this->error($message);
        exit;
    }
}