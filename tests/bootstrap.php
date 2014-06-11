<?php

/* @var \Composer\Autoload\ClassLoader $loader */

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->addPsr4('Samba\\', 'tests/Samba/');
