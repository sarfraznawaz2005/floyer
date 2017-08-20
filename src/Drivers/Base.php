<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 8/16/2017
 * Time: 4:21 PM
 */

namespace Sarfraznawaz2005\Floyer\Drivers;

use Sarfraznawaz2005\Floyer\Contracts\ConnectorInterface;
use Sarfraznawaz2005\Floyer\Traits\IO;
use Sarfraznawaz2005\Floyer\Traits\Options;

Abstract class Base
{
    use IO;
    use Options;

    protected $lastCommitId = '';
    protected $lastCommitIdRemote = '';
    protected $revFile = '.rev_floyer';
    protected $zipFile = 'deployment_floyer.zip';
    protected $extractScriptFile = 'extract_floyer.php';

    // console-related
    public $io = null;

    protected $options = [];
    protected $connector = null;

    protected $filesToDelete = [];
    protected $filesChanged = [];
    protected $filesToExclude = [];

    public function init(ConnectorInterface $connector)
    {
        $this->connector = $connector;

        $this->lastCommitId = $this->lastCommitIdLocal();

        $this->options = $this->getOptions();

        $this->filesToExclude = array_merge($this->filesToExclude, $this->options['exclude']);
    }

    public function exec($command)
    {
        $command = str_replace("\n", "", $command);

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

        return [
            'files' => $files,
            'filesToSkip' => $filesToSkip,
        ];
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
        $root = $this->options['root'];

        $zipFile = $this->zipFile;

        $script = <<< SCRIPT
<?php 
   set_time_limit(0);
   
   \$root = \$_SERVER['DOCUMENT_ROOT'] . '$root';
    
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

        return $script;
    }
}