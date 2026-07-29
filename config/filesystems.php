<?php

return [

    'disks' => [
        'passgenerator' => [
            'driver' => 'local',
            'root' => storage_path('app/passgenerator'),
        ],
        'webroot' => [
            'driver' => 'local',
            'root' => public_path(),    // 👈 now Glide reads from /public
        ],
    ],

];
