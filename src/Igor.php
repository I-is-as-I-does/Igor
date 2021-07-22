<?php
/* This file is part of Igor | SSITU | (c) 2021 I-is-as-I-does | MIT License */
namespace SSITU\Igor;

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
    protected $intrNamespace;

    public function set_srcFilesGlobpattrn(string $pattern)
    {
        $this->srcFilesGlobpattrn = $pattern;
        return $this;
    }
    public function set_intrFilesGlobpattrn(string $pattern)
    {
        $this->intrFilesGlobpattrn = $pattern;
        return $this;
    }

    public function set_intrFilesSuffx(string $suffx)
    {
        $this->intrFilesSuffx = $suffx;
        return $this;
    }
    public function set_intrFilesPrefx(string $prefx)
    {
        $this->intrFilesPrefx = $prefx;
        return $this;
    }

    public function set_licenseMention(string $mention)
    {
        $this->licenseMention = $mention;
        return $this;
    }
    public function set_autoSearchLicense(bool $bool)
    {
        $this->autoSearchLicense = $bool;
        return $this;
    }
    public function set_rewrite(bool $bool)
    {
        $this->rewrite = $bool;
        return $this;
    }
    public function set_addImplementsToSrc(bool $bool)
    {
        $this->addImplementsToSrc = $bool;
        return $this;
    }

    public function set_intrNamespace(string $intrNamespace)
    {
        $this->intrNamespace = $this->fixIntrNamespace($intrNamespace);
        return $this;
    }

    public function doALLtheInterfaces(string $srcDir, string $intrDir)
    {
        foreach ([$srcDir, $intrDir] as $dir) {
            if (!is_dir($dir)) {
                return ['err' => 'not a dir, my dear: ' . $srcDir];
            }
        }
        $this->srcDir = trim($srcDir, ' \n\r\t\v\0/\\') . '/';
        if (empty($this->srcFilesGlobpattrn)) {
            $this->guess_srcFilesGlobpattrn();
        }

        $this->srcFiles = glob($this->srcDir . $this->srcFilesGlobpattrn);
        if (empty($this->srcFiles)) {
            return ['err' => 'no src file found here: ' . $srcDir];
        }

        $this->set_intrNamespace($intrNamespace);
        if ($this->rewrite !== true) {
            return $this->filterFiles();
        }

        return $this->buildIterator();
    }

    public function doOneInterface(string $srcPath, string $destPath)
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
        $stock[] = 'namespace ' . $this->intrNamespace . ';';

        $stock[] = 'interface ' . $intrname . ' {';

        include $srcPath;

        $fullclassn = substr($namespace, 10, -1) . '\\' . $classname;
        if (!class_exists($fullclassn)) {
            return ['err' => 'could not call ' . $fullclassn];
        }
        $reflc = new \ReflectionClass($fullclassn);
        $methods = $reflc->getMethods(\ReflectionProperty::IS_PUBLIC);
        foreach ($methods as $method) {
            if (!empty($method)) {
                $statc = ' ';
                if ($method->isStatic()) {
                    $statc = ' static ';
                }
                $stock[] = 'public' . $statc . 'function ' . $method->getName() . '(' . $this->getParamListAndDflts($method) . ');';
            }
        }
        $stock[] = '}';
        $stock = implode(PHP_EOL, $stock);

        $write = file_put_contents($destPath, $stock, LOCK_EX);
        if (!$write) {
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
                    $rslt[] = $this->doInterface($srcPath, $this->intrDir . $matchIntfName);
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
            $rslt[] = $this->doInterface($srcPath, $destPath);
        }
        return $rslt;
    }

    protected function checkContent($splithead)
    {
        return (count($splithead) > 1 && stripos($splithead[0], 'abstract') === false);
    }

    protected function fixIntrNamespace($intrNamespace)
    {
        $intrNamespace = trim($intrNamespace);
        if (empty($intrNamespace)) {
            return '';
        }
        if (stripos($intrNamespace, 'namespace') !== false) {
            $intrNamespace = substr($intrNamespace, 10);
        }
        return trim($intrNamespace, ";");
    }

    protected function addImplements($srcPath, $rawcntent, $intrname, $classname)
    {
        $pattern = '/(class\s+\w+\s*(extends\s+\w+\s*)?)((implements)\s+[\w,\s,\\\]+)?\s*/i';
        $preg = preg_match($pattern, $rawcntent, $matches);

        $fullIntrName = '\\' . $this->intrNamespace . '\\' . $intrname;
        $lastMatch = trim(strtolower(end($matches)));
        $prev = trim(prev($matches));

        if ($lastMatch == 'implements') {
            if (strpos($prev, $intrname) !== false) {
                return ['skipped' => '"' . $classname . '" already implements "' . $intrname . '"'];
            }
            $impl = $prev . ', ' . $fullIntrName;
        } else {
            $impl = 'implements ' . $fullIntrName;
        }

        $split = explode($prev, $rawcntent);
        $nwcontent = implode($impl, $split);
        $save = file_put_contents($srcPath, $nwcontent, LOCK_EX);
        if ($save === false) {
            return ['err' => 'impossible to save edited src file; provided path: ' . $srcPath];
        }
        return ['success' => '"' . $classname . '" now implements "' . $intrname . '"'];

    }

    protected function getAllTypes($param)
    {
        $output = '';
        if ($reflcType = $param->getType()) {

            if ($reflcType instanceof \ReflectionUnionType) {
                $types = $reflcType->getTypes();
                $typesNames = [];
                foreach ($types as $type) {
                    if ($name = $type->getName()) {
                        $typesNames[] = $name;
                    }
                }
                $output = implode('|', $typesNames);
            } else {
                $name = $reflcType->getName();
                if ($name != 'mixed' && $param->allowsNull()) {
                    $output = '?' . $name;
                } else {
                    $output = $name;
                }
            }

        }
        return $output;
    }

    protected function getParamListAndDflts($method)
    {
        $params = $method->getParameters();
        $stack = [];
        if (!empty($params)) {
            foreach ($params as $param) {
                $mthline = $this->getAllTypes($param);
                $mthline .= ' $' . $param->getName();

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

        $intrDir = trim($intrDir, ' \n\r\t\v\0/\\') . '/';
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

}
