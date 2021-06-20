<?php
/* This file is part of Igor | SSITU | (c) 2021 I-is-as-I-does | MIT License */
namespace SSITU\Igor;

use SSITU\Jack\Jack;

class Igor implements Igor_i
{
    public $srcFilesGlobpattrn = "*[!(_i)].php";
    public $intrFilesGlobpattrn;
    public $intrFilesSuffx = "_i";
    public $intrFilesPrefx;
    public $licenseMention;
    public $autoSearchLicense = true;
    public $rewrite = true;
    public $addImplementsToSrc = true;

    protected $srcDir;
    protected $intrDir;

    protected $srcFiles;

    public function doALLtheInterfaces($srcDir, $intrDir)
    {
        foreach ([$srcDir, $intrDir] as $dir) {
            if (!is_dir($dir)) {
                return ['err' => 'not a dir, my dear: ' . $srcDir];
            }
        }
        $this->srcDir = Jack::File()->reqTrailingSlash($srcDir);
        if (empty($this->srcFilesGlobpattrn)) {
            $this->guess_srcFilesGlobpattrn();
        }

        $this->srcFiles = glob($this->srcDir . $this->srcFilesGlobpattrn);
        if (empty($this->srcFiles)) {
            return ['err' => 'no src file found here: ' . $srcDir];
        }

        $this->intrDir = $this->analyseIntrfdir($intrDir);

        if ($this->rewrite !== true) {
            return $this->filterFiles();
        }

        return $this->buildIterator();
    }

    protected function filterFiles()
    {
        if (empty($this->intrFilesGlobpattrn)) {
            $this->guess_intrFilesGlobpattrn();
        }
        $intrFiles = glob($this->intrDir . $this->intrFilesGlobpattrn);
        if (!empty($intrFiles)) {
            $rslt = [];
            $baseIntfiles = array_map(function ($it) {return basename($it);}, $intrFiles);
            foreach ($this->srcFiles as $srck => $srcPath) {
                $matchIntfName = $this->matchIntfName($srcPath);
                if (!in_array($matchIntfName, $baseIntfiles)) {
                    $rslt[] = $this->doOneInterface($srcPath, $this->intrDir . $matchIntfName);
                }
            }
            return $rslt;
        }
        return $this->buildIterator();
    }

    protected function matchIntfName($path)
    {
        $basename = basename($path, '.php');
        if (!empty($this->intrFilesSuffx)) {
            $basename .= $this->intrFilesSuffx;
        }
        if (!empty($this->intrFilesPrefx)) {
            $basename = $this->intrFilesPrefx . $basename;
        }
        return $basename . '.php';
    }

    protected function buildIterator()
    {
        $rslt = [];
        foreach ($this->srcFiles as $srck => $srcPath) {
            $destPath = $this->intrDir . $this->matchIntfName($srcPath);
            $rslt[] = $this->doOneInterface($srcPath, $destPath);
        }
        return $rslt;
    }

    protected function checkContent($splithead)
    {
        return (count($splithead) > 1 && stripos($splithead[0], 'abstract') === false);
    }

    public function doOneInterface($srcPath, $destPath)
    {
        if ($srcPath == $destPath) {
            return ['err' => 'Nope: src path and dest path are the same; this would erase src file'];
        }
        $rawcntent = file_get_contents($srcPath);
        if (empty($rawcntent)) {
            return ['err' => 'cannot read ' . $srcPath];
        }

        $splithead = explode('class ', $rawcntent);
        if (!$this->checkContent($splithead)) {
            return ['skipped' => 'seems not eligible: ' . $srcPath];
        }
        $stock = [];
        $stock[] = '<?php';

        $classnpattern = '/\w+(?=(\s+|\{))/i';
        $matchclassname = \preg_match($classnpattern, $splithead[1], $matches);
        $classname = $matches[0];
        $intrname = basename($destPath, '.php');
        $namespace = $this->determineNamespace($splithead[0]);
        $stock[] = $this->determineLicense($splithead[0]);
        $stock[] = $namespace;
        $stock[] = 'interface ' . $intrname . ' {';
           
        include $srcPath;

        $fullclassn = substr($namespace, 10, -1) . '\\' . $classname;

        $methods = get_class_methods($fullclassn);
        foreach ($methods as $method) {
            if (!empty($method)) {
                $stock[] = 'public function ' . $method . '(' . $this->getParamListAndDflts($fullclassn, $method) . ');';
            }
        }
        $stock[] = '}';
        $stock = implode(PHP_EOL, $stock);
        $write = file_put_contents($destPath, $stock, LOCK_EX);
        if ($write === false) {
            ['skipped' => 'impossible to save interface "' . $intrname . '"'];
        }
        $rslt = ['success' => $classname];
        if ($this->addImplementsToSrc) {
            $adt = $this->addImplements($srcPath, $rawcntent, $intrname, $classname);
            $adtkey = array_key_first($adt);
            $rslt[$adtkey] = $adt[$adtkey];
        }
        return $rslt;
    }

    protected function addImplements($srcPath, $rawcntent, $intrname, $classname)
    {
        $pattern = '/(class\s+\w+\s*(extends\s+\w+\s*)?)((implements)\s+[\w,\s]+)?\s*{/i';
        $preg = preg_match($pattern, $rawcntent, $matches);
        $lastk = array_key_last($matches);
        if (strtolower($matches[$lastk]) == 'implements') {
            $prev = $matches[$lastk - 1];
            if (strpos($prev, $intrname) !== false) {
                return ['skipped' => '"' . $classname . '" already implements "' . $intrname . '"'];
            }
            $impl = trim($prev) . ', ' . $intrname;
        } else {
            $impl = 'implements ' . $intrname;
        }
        $old = $matches[1];
        $split = explode($old, $rawcntent);
        $nw = trim($old) . ' ' . $impl . ' ';
        $nwcontent = implode($nw, $split);
        $save = file_put_contents($srcPath, $nwcontent, LOCK_EX);
        if ($save === false) {
            return ['err' => 'impossible to save edited src file; provided path: ' . $srcPath];
        }
        return ['success' => $classname . '" now implements "' . $intrname . '"'];

    }

    protected function getParamListAndDflts($classn, $method)
    {
        $reflc = new \ReflectionMethod($classn, $method);
        $params = $reflc->getParameters();
        $stack = [];
        if (!empty($params)) {
            foreach ($params as $param) {

                $mthline = '$' . $param->getName();

                if ($param->isOptional()) {
                    $dflt = $param->getDefaultValue();
                    $exportDflt = var_export($dflt, true);
                    $dflt = preg_replace('/((?<=array\s\()\s+|\s+(?=\)))/', '', $exportDflt);
                    $mthline .= ' = ' . $dflt;
                }
                $stack[] = $mthline;
            }
        }
        return implode(', ', $stack);
    }

    protected function determineNamespace($head)
    {
        $pattern = '/(namespace )[\w\_\\\]+;/i';
        $rslt = preg_match($pattern, $head, $matches);
        if (!empty($matches)) {
            return $matches[0];
        }
        return '';
    }

    protected function determineLicense($head)
    {
        if (!empty($this->licenseMention)) {
            return $this->licenseMention;
        }
        if ($this->autoSearchLicense === true) {
            $pattern = '/(\/\*|#|\/\/).*(?=license).*(?=(\*\/|\n|\r))/i';
            $rslt = preg_match($pattern, $head, $matches);
            if (!empty($matches)) {
                return $matches[0];
            }
        }
        return '';
    }

    protected function analyseIntrfdir($intrDir)
    {

        $intrDir = Jack::File()->reqTrailingSlash($intrDir);
        if (in_array($intrDir, ['/', './'])) {
            $intrDir = $this->srcDir;
        }
        return $intrDir;
    }
    protected function guess_intrFilesGlobpattrn()
    {
        $pattern = "*";
        if (!empty($this->intrFilesSuffx)) {
            $pattern .= trim($this->intrFilesSuffx, '.php');
        }
        if (!empty($this->intrFilesPrefx)) {
            $pattern = $this->intrFilesPrefx . $pattern;
        }
        $this->intrFilesGlobpattrn = $pattern . '.php';
    }

    protected function guess_srcFilesGlobpattrn()
    {
        if (empty($this->srcFilesGlobpattrn)) {
            $pattern = "*";
            if (!empty($this->intrFilesSuffx)) {
                $pattern .= '[!(' . trim($this->intrFilesSuffx, '.php') . ')]';
            }
            if (!empty($this->intrFilesPrefx)) {
                $pattern = '[!(' . $this->intrFilesPrefx . ')]' . $pattern;
            }
            $this->srcFilesGlobpattrn = $pattern . '.php';
        }
    }

    public function set_srcFilesGlobpattrn(string $pattern)
    {
        $this->srcFilesGlobpattrn = $pattern;
    }
    public function set_intrFilesGlobpattrn(string $pattern)
    {
        $this->intrFilesGlobpattrn = $pattern;
    }

    public function set_intrFilesSuffx(string $suffx)
    {
        $this->intrFilesSuffx = $suffx;
    }
    public function set_intrFilesPrefx(string $prefx)
    {
        $this->intrFilesPrefx = $prefx;
    }

    public function set_licenseMention(string $mention)
    {
        $this->licenseMention = $mention;
    }
    public function set_autoSearchLicense(bool $bool)
    {
        $this->autoSearchLicense = $bool;
    }
    public function set_rewrite(bool $bool)
    {
        $this->rewrite = $bool;
    }
    public function set_addImplementsToSrc(bool $bool)
    {
        $this->addImplementsToSrc = $bool;
    }
}
