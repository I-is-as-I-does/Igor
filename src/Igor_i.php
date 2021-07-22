<?php
/* This file is part of Igor | SSITU | (c) 2021 I-is-as-I-does | MIT License */
namespace SSITU\Igor;

interface Igor_i
{
    public function doALLtheInterfaces($srcdir, $intfdir, $intrNamespace);
    public function doOneInterface($srcfile, $dest, $intrNamespace);
    public function set_srcFilesGlobpattrn(string $pattern);
    public function set_intrFilesGlobpattrn(string $pattern);
    public function set_intrFilesSuffx(string $suffx);
    public function set_intrFilesPrefx(string $prefx);
    public function set_licenseMention(string $mention);
    public function set_autoSearchLicense(bool $bool);
    public function set_rewrite(bool $bool);
    public function set_addImplementsToSrc(bool $bool);
}
