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
use Sarfraznawaz2005\Floyer\Contracts\ConnectorInterface;
use Sarfraznawaz2005\Floyer\Traits\IO;
use Sarfraznawaz2005\Floyer\Traits\Options;
use ZipArchive;

Abstract class Base
{
    use IO;
    use Options;

    protected $lastCommitId = '';
    protected $lastCommitIdRemote = '';
    protected $revFile = '.rev_floyer';
    protected $zipFile = 'deployment_floyer.zip';
    protected $extractScriptFile = 'extract_floyer.php';
    protected $exportFolder = 'floyer_svn_export';
    protected $dir = '';
    protected $tmpDir = '';

    // console-related
    public $io = null;

    protected $options = [];
    protected $connector = null;

    protected $filesToDelete = [];
    protected $filesChanged = [];
    protected $filesToExclude = [];

    public function init(ConnectorInterface $connector)
    {
        $this->dir = getcwd() . DIRECTORY_SEPARATOR;

        $this->connector = $connector;

        $this->lastCommitId = $this->lastCommitIdLocal();

        $this->options = $this->getOptions();

        $this->revFile = $this->options['revision_file_name'];

        $this->tmpDir = $this->tmpDir();
        $this->zipFile = $this->tmpDir . $this->zipFile;
        $this->extractScriptFile = $this->tmpDir . $this->extractScriptFile;
        $this->exportFolder = $this->tmpDir . $this->exportFolder;

        $this->filesToExclude = array_merge($this->filesToExclude, $this->options['exclude']);
    }

    public function exec($command)
    {
        $command = str_replace("\n", "", $command);

        // check debug mode
        if (isset($this->options['debug']) && $this->options['debug'] == 1) {
            $this->line('DEBUG: ' . $command);
        }

        return shell_exec(escapeshellcmd($command) . ' 2>&1');
    }

    /**
     * Filter ignore files.
     *
     * @param array $files Array of files which needed to be filtered
     *
     * @return array with `files` (filtered) and `filesToSkip`
     */
    public function filterIgnoredFiles($files)
    {
        $filesToSkip = [];

        foreach ($files as $i => $file) {
            foreach ($this->filesToExclude as $pattern) {
                if ($this->patternMatch($pattern, $file)) {
                    unset($files[$i]);
                    $filesToSkip[] = $file;
                    break;
                }
            }
        }

        $files = array_values($files);

        return $files;
    }

    /**
     * Glob the file path.
     *
     * @param string $pattern
     * @param string $string
     *
     * @return string
     */
    protected function patternMatch($pattern, $string)
    {
        return preg_match('#^' . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . '$#i', $string);
    }

    protected function extractScript()
    {
        $userRoot = $this->options['root'];
        $zipFile = basename($this->zipFile);

        return <<< SCRIPT
<?php 
   set_time_limit(0);
   
    \$root = \$_SERVER['DOCUMENT_ROOT'] . '/';
    
    if (false === strpos(\$root, '$userRoot')) {
        \$root = \$_SERVER['DOCUMENT_ROOT'] . "/$userRoot";
    }   
    
  \$zip = new ZipArchive();
  \$res = \$zip->open("\$root/$zipFile");

  if (\$res === true){
    \$zip->extractTo("\$root");
    \$zip->close();
    echo 'ok';
  } else {
    echo 'failed';
  }

SCRIPT;

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

    protected function tmpDir()
    {
        $dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $dir = trim($dir);

        if ($dir && substr($dir, -1, 1) !== '/') {
            $dir = $dir . '/';
            return $dir;
        }

        return '';
    }
}