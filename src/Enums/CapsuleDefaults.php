<?php namespace DreamFactory\Enterprise\Instance\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

class CapsuleDefaults extends FactoryEnum
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string The default capsule path
     */
    const DEFAULT_ROOT_PATH = '/data/capsules';
    /**
     * @type string The default instance installation path
     */
    const DEFAULT_INSTANCE_INSTALL_PATH = '/var/www/launchpad';
}
