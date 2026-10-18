<?php

/*
| The YAML under .github is generated from this file.
| Regenerate it with: vendor/bin/testbench slipway:compile
*/

return [

    'name' => 'CI',

    'drivers' => [
        'github' => true,
    ],

    'strict' => false,
    'guard' => true,
    'lockfile' => false,
    'artisan' => 'vendor/bin/testbench',

    'php' => ['8.2', '8.3', '8.4'],
    'node' => '22',

    'triggers' => [
        'push' => ['main'],
        'tags' => [],
        'pull_request' => true,
        'schedule' => ['0 3 * * 1'],
        'manual' => true,
        'paths_ignore' => [],
    ],

    'concurrency' => ['cancel_superseded' => true],
    'timeouts' => ['default' => 15],
    'cache' => true,
    'env' => [],

    'stages' => [
        'quality' => ['pint', 'composer-validate' => ['strict' => false], 'composer-audit', 'secret-scan'],

        'test' => [
            'matrix' => true,
            'steps' => ['phpunit' => ['prepare_env' => false]],
        ],

        'frontend' => ['npm-test'],
    ],

    'environments' => [],
    'policies' => [],
    'secrets' => [],

];
