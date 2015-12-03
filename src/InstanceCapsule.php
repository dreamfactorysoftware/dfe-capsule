<?php namespace DreamFactory\Enterprise\Instance\Capsule;

use DreamFactory\Enterprise\Common\Traits\EntityLookup;
use DreamFactory\Enterprise\Database\Models\Instance;
use DreamFactory\Enterprise\Instance\Enums\CapsuleDefaults;
use DreamFactory\Library\Utility\Disk;
use Illuminate\Contracts\Foundation\Application;

class InstanceCapsule
{
    //******************************************************************************
    //* Traits
    //******************************************************************************

    use EntityLookup;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string The base path of all capsules
     */
    protected $basePath;
    /**
     * @type Application The encapsulated instance
     */
    protected $capsule;
    /**
     * @type string The absolute path to the capsule's base path
     */
    protected $capsuleBasePath;
    /**
     * @type string Our capsule ID
     */
    protected $id;
    /**
     * @type Instance The current instance
     */
    protected $instance;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Capsule constructor.
     *
     * @param Instance|string $instance The instance or instance-id to encapsulate
     * @param string|null     $basePath The base path of all capsules
     */
    public function __construct($instance, $basePath = null)
    {
        $this->initialize($basePath);
        $this->instance = $this->findInstance($instance);
        $this->id = sha1($this->instance->cluster->cluster_id_text . '.' . $this->instance->instance_id_text);
    }

    /**
     * Boot up the capsule
     *
     * @return InstanceCapsule
     */
    public function up()
    {
        $this->encapsulateStorage();

        return $this;
    }

    /**
     * Shut down capsule
     */
    public function down()
    {
        if (!Disk::deleteTree($this->capsuleBasePath)) {
            throw new \RuntimeException('Unable to remove capsule path "' . $this->capsuleBasePath . '".');
        }
    }

    protected function encapsulateStorage()
    {
        $_path = Disk::path([$this->basePath, $this->id], true);

        $_links = config('capsule.instance.symlinks', []);
        $_blueprint = config('capsule.storage.blueprint', []);

        foreach ($_links as $_link) {
            $_linkTarget = Disk::path([$_path, $_link]);
            if (false === symlink($_linkTarget, $_link)) {
            }
        }

        symlink($_source)
    }

    /**
     * @param string|null $basePath The base path of all capsules
     */
    protected function initialize($basePath = null)
    {
        $this->basePath = $basePath ?: config('capsule.base-path', CapsuleDefaults::DEFAULT_BASE_PATH);

        if (!is_dir($this->basePath)) {
            if (!Disk::ensurePath($this->basePath)) {
                throw new \RuntimeException('Cannot create, or write to, base-path "' . $this->basePath . '".');
            }
        }
    }
}
