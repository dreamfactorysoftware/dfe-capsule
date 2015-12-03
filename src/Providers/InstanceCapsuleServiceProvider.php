<?php namespace DreamFactory\Enterprise\Instance\Capsule\Providers;

use DreamFactory\Enterprise\Common\Providers\BaseServiceProvider;
use DreamFactory\Enterprise\Instance\Capsule\Services\InstanceCapsuleService;

class InstanceCapsuleServiceProvider extends BaseServiceProvider
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /** @inheritdoc */
    const IOC_NAME = 'dfe.instance-capsule';

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->singleton(static::IOC_NAME,
            function ($app) {
                return new InstanceCapsuleService($app);
            });
    }
}
