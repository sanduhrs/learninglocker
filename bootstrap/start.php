<?php

use \Helpers\Helpers as Helpers;

$app = new Illuminate\Foundation\Application;

$env = $app->detectEnvironment(function () use ($app) {
  // Attempts to set the environment using the hostname (env => hostname).
  $env = Helpers::getEnvironment([
    'local' => ['your-machine-host-name']
  ], gethostname());
  if ($env) return $env;

  // Attempts to set the environment using the domain (env => domain).
  $env = Helpers::getEnvironment([
    'local' => ['127.0.0.1', 'localhost', 'dev.ll']
    // 'production' => ['*.example.com']
  ], $app['request']->getHost());
  if ($env) return $env;

  // Sets environment using LARAVEL_ENV server variable if it's set.
  if (array_key_exists('LARAVEL_ENV', $_SERVER)) {
    return $_SERVER['LARAVEL_ENV'];
  }

  // Otherwise sets the environment to production or the test environment if unit testing.
  return 'production';
});

$app->bindInstallPaths(require __DIR__.'/paths.php');
$framework = $app['path.base'].'/vendor/laravel/framework/src';

require $framework.'/Illuminate/Foundation/start.php';
return $app;
