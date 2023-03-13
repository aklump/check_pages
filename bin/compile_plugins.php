#!/usr/bin/env php
<?php

/**
 * @file
 * Compile the plugins into the main app.
 */

use AKlump\CheckPages\Parts\Runner;
use AKlump\CheckPages\Plugin\PluginsCompiler;
use AKlump\CheckPages\Plugin\PluginsManager;
use Symfony\Component\Console\Input\ArrayInput;

define('ROOT', realpath(__DIR__ . '/../'));

require_once ROOT . '/vendor/autoload.php';

$compiler = new PluginsCompiler(
  new PluginsManager(new Runner(ROOT, new ArrayInput([]), new Symfony\Component\Console\Output\NullOutput()), ROOT . '/plugins'),
  ROOT . '/schema.suite.json',
  ROOT . '/' . Runner::SCHEMA_VISIT . '.json',
  ROOT . '/services.yml',
  ROOT . '/services.DO_NOT_EDIT.yml',
  ROOT . '/composer.json',
  ROOT . '/example'
);
$compiler->compile();

system('composer dump; composer update');