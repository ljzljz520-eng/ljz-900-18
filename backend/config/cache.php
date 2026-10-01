<?php
$runtime = defined('RUNTIME_PATH') ? RUNTIME_PATH : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR);
return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => 'File',
            'path' => $runtime . 'cache' . DIRECTORY_SEPARATOR,
            'prefix' => '',
            'expire' => 0,
        ],
    ],
];
