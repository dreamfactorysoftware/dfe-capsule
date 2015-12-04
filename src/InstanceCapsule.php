<?php namespace DreamFactory\Enterprise\Instance\Capsule;

use DreamFactory\Enterprise\Common\Traits\EntityLookup;
use DreamFactory\Enterprise\Common\Traits\Lumberjack;
use DreamFactory\Enterprise\Database\Models\Instance;
use DreamFactory\Enterprise\Instance\Enums\CapsuleDefaults;
use DreamFactory\Library\Utility\Disk;
use Illuminate\Contracts\Foundation\Application;

class InstanceCapsule
{
    //******************************************************************************
    //* Traits
    //******************************************************************************

    use EntityLookup, Lumberjack;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Application The encapsulated instance
     */
    protected $capsule;
    /**
     * @type string The path to this capsule (i.e. /data/capsules/hashed-instance-name)
     */
    protected $capsulePath;
    /**
     * @type string The path to the root of all capsule's (i.e. /data/capsules)
     */
    protected $capsuleRootPath;
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
     * @param Instance|string $instance        The instance or instance-id to encapsulate
     * @param string|null     $capsuleRootPath The root path of all capsules
     */
    public function __construct($instance, $capsuleRootPath = null)
    {
        $this->initialize($capsuleRootPath);

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
        $this->encapsulate();

        return $this;
    }

    /**
     * Shut down capsule
     *
     * @param bool $keep If true, the instance capsule will not be removed.
     */
    public function down($keep = false)
    {
        //  A keeper or no id? No way!
        if ($keep || empty($this->id)) {
            return;
        }

        $_path = $this->capsulePath ?: Disk::path([$this->capsuleRootPath, $this->id], true);

        if (!Disk::deleteTree($_path)) {
            throw new \RuntimeException('Unable to remove capsule path "' . $_path . '".');
        }
    }

    /**
     * Create the necessary links to run the instance
     *
     * @return bool
     */
    protected function encapsulate()
    {
        $_capsulePath = Disk::path([$this->capsuleRootPath, $this->id], true);
        $_targetPath = config('capsule.instance.install-path', CapsuleDefaults::DEFAULT_INSTANCE_INSTALL_PATH);
        $_links = config('capsule.instance.symlinks', []);

        //  Create symlinks
        foreach ($_links as $_link) {
            $_linkTarget = Disk::path([$_targetPath, $_link]);

            if (false === symlink($_linkTarget, Disk::path([$_capsulePath, $_link]))) {
                $this->error('Error symlinking target "' . $_linkTarget . '"');

                try {
                    $this->down();
                } catch (\Exception $_ex) {
                    $this->error('Error removing capsule remnants.');
                }

                return false;
            }
        }

        $_capsulePath .= DIRECTORY_SEPARATOR;

        //  Create an env
        if (!file_exists($_capsulePath . '.env')) {
            file_put_contents($_capsulePath . '.env', file_get_contents($_targetPath . '.env'));
        }

        $this->capsulePath = $_capsulePath;

        return true;
    }

    /**
     * @param string|null $capsuleRootPath The root path of all capsules
     */
    protected function initialize($capsuleRootPath = null)
    {
        $this->capsuleRootPath = $capsuleRootPath ?: config('capsule.root-path', CapsuleDefaults::DEFAULT_ROOT_PATH);

        if (!is_dir($this->capsuleRootPath) && !Disk::ensurePath($this->capsuleRootPath)) {
            throw new \RuntimeException('Cannot create, or write to, capsule.root-path "' .
                $this->capsuleRootPath .
                '".');
        }
    }
}
