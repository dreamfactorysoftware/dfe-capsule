<?php
//******************************************************************************
//* Capsule-related settings
//******************************************************************************

use DreamFactory\Enterprise\Instance\Enums\CapsuleDefaults;
use DreamFactory\Library\Utility\Disk;

return [
    /** The root path to all capsules */
    'root-path' => env('DFE_CAPSULE_ROOT_PATH', CapsuleDefaults::DEFAULT_ROOT_PATH),
    /** Static directories which can be symlinked */
    'instance'  => [
        'symlinks' => [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'vendor',
        ],
    ],
    'storage'   => [
        /** The blueprint for storage */
        'blueprint' => [
            'app',
            'databases',
            'framework',
            Disk::segment(['framework', 'sessions']),
            Disk::segment(['framework', 'views']),
            'logs',
        ],
    ],
];
