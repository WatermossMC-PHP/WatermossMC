#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Watermoss\Server;

$cfgFile = __DIR__ . '/../server.properties';
$server = new Server($cfgFile);
$server->start();
