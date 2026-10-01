<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

defined('RUNTIME_PATH') || define('RUNTIME_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR);
$app = new \think\App(__DIR__ . '/../');
$http = $app->http;
$response = $http->run();
$response->send();
$http->end($response);
