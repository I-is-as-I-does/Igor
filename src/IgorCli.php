<?php
/* This file is part of Igor | SSITU | (c) 2021 I-is-as-I-does | MIT License */
namespace SSITU\Igor;

use \SSITU\Euclid\EuclidCompanion;

class IgorCli
{
    private $Companion;
    private $Igor;
    private $callableMap = [1 => 'do ALL the interfaces', 2 => 'do one interface'];

    private $src;
    private $dst;
    private $key;
    private $nms;

    public function __construct($runBuild = false)
    {
        $this->Companion = EuclidCompanion::inst();
        $this->Igor = new Igor();
        if ($runBuild) {
            return $this->build();
        }
    }

    private function reset()
    {
        $this->src = '';
        $this->dst = '';
        $this->key = '';
        $this->nms = '';
    }

    public function build()
    {
        $this->Companion->set_callableMap($this->callableMap);
        $this->Companion::echoDfltNav();
        $this->key = $this->Companion->printCallableAndListen();
        return $this->transition();
    }

    private function transition()
    {
        $this->Companion->set_callableMap([]);
        return $this->setSrc();
    }

    private function setSrc()
    {
        $next = 'Set source ';
        if ($this->key == 1) {
            $next .= 'dir';
        } else {
            $next .= 'path';
        }
        $next .= ' > ';
        $this->Companion::msg($next);
        $resp = $this->Companion->listenToRequest();
        if (($this->key == 1 && !is_dir($resp)) || ($this->key == 2 && !is_file($resp))) {
            $this->Companion::msg('Unvalid path', 'yellow');
            return $this->setSrc();
        }
        $this->src = $resp;
        return $this->setDst();
    }
    private function setDst()
    {
        $next = 'Set destination ';
        if ($this->key == 1) {
            $next .= 'dir';
        } else {
            $next .= 'path';
        }
        $next .= ' > ';
        $this->Companion::msg($next);
        $resp = $this->Companion->listenToRequest();
        if (($this->key == 1 && !is_dir($resp)) || ($this->key == 2 && !is_dir(dirname($resp)))) {
            $this->Companion::msg('Unvalid path', 'yellow');
            return $this->setDst();
        }
        if ($this->key == 2 && $this->src === $resp) {
            $this->Companion::msg('Nope: src path and dest path are the same; this would erase src file', 'yellow');
            return $this->setDst();
        }
        $this->dst = $resp;
        return $this->setNms();
    }

    private function setNms()
    {    $s = '';
        if ($this->key == 1) {
            $s = 's';
        }
        $next = 'Set interface'.$s .' namespace >';
        
        $this->Companion::msg($next);
        $resp = $this->Companion->listenToRequest();
        if (!empty($resp) && strpos($resp, '\\') === false) {
            $this->Companion::msg('Sorry, seems like an unvalid namespace', 'yellow');
            return $this->setNamespace();
        }
        $this->nms = $resp;
        return $this->handleCmd();
    }

    private function handleCmd()
    {
        if (empty($this->key) || empty($this->dst) || empty($this->src)) {
            return false;
        }

        $method = 'doOneInterface';
        if ($this->key == 1) {
            $method = 'doALLtheInterfaces';
        }
        $build = $this->Igor->$method($this->src, $this->dst, $this->nms);
        $this->reset();
        $this->key = $this->Companion->printRslt($build, false, true, $this->callableMap);
        return $this->transition();
    }

}
