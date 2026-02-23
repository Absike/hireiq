<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'vue' => [
        'version' => '3.5.22',
    ],
    '@vue/runtime-dom' => [
        'version' => '3.5.22',
    ],
    '@vue/runtime-core' => [
        'version' => '3.5.22',
    ],
    '@vue/shared' => [
        'version' => '3.5.22',
    ],
    '@vue/reactivity' => [
        'version' => '3.5.22',
    ],
    'vue-router' => [
        'version' => '5.0.3',
    ],
    '@vue/devtools-api' => [
        'version' => '7.7.7',
    ],
    'pinia' => [
        'version' => '3.0.4',
    ],
    'axios' => [
        'version' => '1.13.5',
    ],
];
