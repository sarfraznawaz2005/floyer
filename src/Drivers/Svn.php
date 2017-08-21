<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:21 PM
 */

namespace Sarfraznawaz2005\Floyer\Drivers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sarfraznawaz2005\Floyer\Contracts\DriverInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ZipArchive;

class Svn extends Base implements DriverInterface
{
    protected $exportFolder = 'floyer_svn_export';

    /**
     * Starts deployment process
     */
    function processDeployment()
    {
        $this->title('Getting list of changed files...');

        @unlink($this->zipFile);
        @unlink($this->extractScriptFile);
        @unlink($this->exportFolder . '.bat');
        $this->recursiveRmDir($this->dir . $this->exportFolder);

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
        @unlink($this->exportFolder . '.bat');
        $this->recursiveRmDir($this->dir . $this->exportFolder);

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

        $prevRevision = $remoteCommitId - 1;
        $command = 'svn diff -r ' . $prevRevision . ':' . $remoteCommitId . ' --summarize';

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

        $this->error('No local revision found.');
        exit;
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
            $this->success("No files to upload!");
            exit;
        }

        $command = 'svn diff -r ' . $remoteCommitId . ':' . $localCommitId . ' --summarize';

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
        $localCommitId = $this->lastCommitId;
        $destinationFolder = $this->dir . $this->exportFolder;
        $remoteCommitId = $this->lastCommitIdRemote;

        // we could not find a way to svn export between two versions via "svn export" command
        // so here we create batch script that will export files in a folder and from there
        // we will use PHP to add these files to zip file. This might not work on non-windows
        // systems.

        if (!$isRollback) {
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

            $command = "$this->exportFolder.bat $remoteCommitId:$localCommitId $this->exportFolder";

            $this->exec($command);

        } else {

            // since in svn revision numbers are in sequence so we just subtract 1
            // to get to one revision before current one.
            $remoteCommitId -= 1;

            foreach ($this->filesChanged as $file) {
                $folder = $destinationFolder . DIRECTORY_SEPARATOR . dirname($file);

                @mkdir($folder, 0777, true);

                // svn export can dig a file out from past revision.
                $command = "svn export -r $remoteCommitId --depth empty -q --force $file $folder";
                $this->exec($command);
            }
        }

        if (!file_exists($destinationFolder)) {
            $this->error('Could not create archive file!');
            exit;
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

            if (in_array($currentFile, $this->filesChanged)) {
                continue;
            }

            if (!$fileInfo->isDir()) {
                @unlink($filename);
            }
        }

        // now create zip file of these files
        $this->zipData($destinationFolder, $this->zipFile);

        @unlink($this->exportFolder . '.bat');
        $this->recursiveRmDir($destinationFolder);
    }

    function checkDirty()
    {
        $status = $this->exec('svn status');

        if (trim($status)) {
            $this->warning('Commit your modifications before deploying.');
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
            $this->warning('No files for to process!');
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
            } elseif (strpos($file, 'fatal') !== false || strpos($file, 'error') !== false) {
                $this->error($file);
                exit;
            }

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

            if ($type && $path) {
                if ($type === 'A' || $type === 'A+' || $type === 'M') {
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
            $this->warning('No files for to process!');
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
                $this->warning('No files for to process!');
            }
            exit;
        }

        // create zip
        $this->success('Creating archive of files to upload...');
        $this->createZipOfChangedFiles($isRollback);

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

        $uploadStatus = $this->connector->upload($this->zipFile, $this->options['root']);

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

            $this->successBG("$type Finished");
        } else {
            $this->error('Unknown Error!');
        }

        @unlink($this->zipFile);
        @unlink($this->extractScriptFile);
    }

    protected function recursiveRmDir($dir)
    {
        if (!file_exists($dir) || !is_dir($dir)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $filename => $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($filename);
            } else {
                unlink($filename);
            }
        }

        @rmdir($dir);
    }

    protected function zipData($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                    continue;
                }

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file));
                } else {
                    if (is_file($file) === true) {

                        $str1 = str_replace($source . '/', '', '/' . $file);
                        $zip->addFromString($str1, file_get_contents($file));

                    }
                }
            }
        } else {
            if (is_file($source) === true) {
                $zip->addFromString(basename($source), file_get_contents($source));
            }
        }

        return $zip->close();
    }
}