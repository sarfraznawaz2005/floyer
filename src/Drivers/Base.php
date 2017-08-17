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
    protected $extractScriptFile = '__extract__.php';

    // console-related
    public $io = null;

    protected $options = [];
    protected $connector = null;

    public function init(ConnectorInterface $connector)
    {
        $this->connector = $connector;

        $this->lastCommitId = $this->lastCommitIdLocal();

        $this->options = $this->getOptions();
    }

    public function exec($command)
    {
        return shell_exec(escapeshellcmd($command) . ' 2>&1');
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