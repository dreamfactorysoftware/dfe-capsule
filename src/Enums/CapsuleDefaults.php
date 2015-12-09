<?php namespace DreamFactory\Enterprise\Instance\Capsule\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

class CapsuleDefaults extends FactoryEnum
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string The default capsule path
     */
    const DEFAULT_PATH = '/data/capsules';
    /**
     * @type string The default capsule log path
     */
    const DEFAULT_LOG_PATH = '/data/logs/capsules';
    /**
     * @type string The default instance installation path
     */
    const DEFAULT_INSTANCE_INSTALL_PATH = '/var/www/launchpad';
}
