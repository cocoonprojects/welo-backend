<?php

namespace FunctionalTest;

error_reporting(E_ALL | E_STRICT);
chdir(dirname(__DIR__));

$path = __DIR__ . '/../../vendor/zendframework/zendframework/library';
putenv("ZF2_PATH=".$path);

$app_root_dir = realpath(__DIR__ . '/../../');
putenv("APP_ROOT_DIR=".$app_root_dir);

if (file_exists($app_root_dir.'/vendor/autoload.php')) {
    include $app_root_dir . '/vendor/autoload.php';
}

echo shell_exec(__DIR__ . '/../../vendor/bin/doctrine-module orm:schema-tool:drop --force');
echo shell_exec(__DIR__ . '/../../vendor/bin/doctrine-module orm:schema-tool:create');
echo shell_exec(__DIR__ . '/../../vendor/bin/doctrine-module dbal:import ' . __DIR__ . '/../sql/init.sql');