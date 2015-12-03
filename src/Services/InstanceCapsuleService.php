<?php namespace DreamFactory\Enterprise\Instance\Capsule\Services;

use DreamFactory\Enterprise\Common\Services\BaseService;
use DreamFactory\Enterprise\Common\Traits\EntityLookup;
use DreamFactory\Enterprise\Database\Models\Instance;
use Illuminate\Contracts\Foundation\Application;

class InstanceCapsuleService extends BaseService
{
    //******************************************************************************
    //* Traits
    //******************************************************************************

    use EntityLookup;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Instance The current instance
     */
    protected $instance;
    /**
     * @type Application The encapsulated instance
     */
    protected $capsule;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create and return an encapsulated instance
     *
     * @param Instance|string $instance The instance model or instance-id
     *
     * @return Application
     */
    public function make($instance)
    {
        $this->instance = $this->findInstance($instance);

        return $this->capsule;
    }

    /**
     * Destroy an encapsulated instance
     *
     * @param \DreamFactory\Enterprise\Database\Models\Instance $instance
     */
    protected function provisionCapsule(Instance $instance)
    {
    }

    /**
     * Destroy an encapsulated instance
     *
     * @param \DreamFactory\Enterprise\Database\Models\Instance $instance
     */
    protected function deprovisionCapsule(Instance $instance)
    {
    }

    protected function createCapsule(Instance $instance)
    {
    }

    protected function destroyCapsule(Capsule $capsule)
    {
    }
}
