<?php namespace DreamFactory\Enterprise\Instance\Capsule\Facades;

use DreamFactory\Enterprise\Instance\Capsule\Providers\InstanceCapsuleServiceProvider;
use Illuminate\Support\Facades\Facade;

class InstanceCapsule extends Facade
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return InstanceCapsuleServiceProvider::IOC_NAME;
    }
}
