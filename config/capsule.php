<?php
//******************************************************************************
//* Capsule-related settings
//******************************************************************************

use DreamFactory\Library\Utility\Disk;

return [
    /** Static directories which can be symlinked */
    'instance' => [
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
    'storage'  => [
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
