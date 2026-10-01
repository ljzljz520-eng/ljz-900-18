<?php
return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'type' => 'local',
            'root' => runtime_path(),
        ],
        'public' => [
            'type' => 'local',
            'root' => public_path(),
            'url' => '/',
        ],
    ],
];
