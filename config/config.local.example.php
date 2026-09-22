<?php
// Copy to config/config.local.php and adjust. That file is git-ignored.
return [
    'app' => [
        'base_url' => 'http://localhost/syscom-growthhub',
        'debug'    => true,
        'secret'   => 'replace-with-a-long-random-string',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'syscom_growthhub',
        'user' => 'root',
        'pass' => '',
    ],
    'audit' => [
        'allow_self' => true, // allow auditing this app's own pages on localhost
    ],
];
