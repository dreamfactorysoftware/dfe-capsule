<?php namespace DreamFactory\Enterprise\Instance\Capsule;

use DreamFactory\Enterprise\Common\Traits\EntityLookup;
use DreamFactory\Enterprise\Common\Traits\Lumberjack;
use DreamFactory\Enterprise\Common\Utility\Ini;
use DreamFactory\Enterprise\Database\Models\Instance;
use DreamFactory\Enterprise\Instance\Enums\CapsuleDefaults;
use DreamFactory\Enterprise\Storage\Facades\InstanceStorage;
use DreamFactory\Library\Utility\Disk;

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
     * Instantiate and bring up an encapsulated instance
     *
     * @param string|Instance $instance
     *
     * @return static
     */
    public static function make($instance)
    {
        $_capsule = new static($instance);
        $_capsule->up();

        return $_capsule;
    }

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
     * Call an artisan command in the encapsulated instance
     *
     * @param string $command   The artisan command to execute
     * @param array  $arguments Arguments to pass. Args = "arg-name" => "arg-value". Options = "--option-name" => "option-value"
     * @param array  $output    An array of the output of the call
     *
     * @return int|bool The return value of the call or false on failure
     */
    public function call($command, $arguments = [], &$output = [])
    {
        if (!$this->capsulePath) {
            return false;
        }

        $_cwd = getcwd();
        if (false === chdir($this->capsulePath)) {
            throw new \LogicException('Capsule path "' . $this->capsulePath . '" is invalid or missing.');
        }

        $_args = [];

        foreach ($arguments as $_key => $_value) {
            $_segment = $_key;

            if (!empty($_value)) {
                $_segment .= (('--' == substr($_key, 0, 2)) ? '=' : ' ') . escapeshellarg($_value);
            }

            $_args[] = $_segment;
        }

        exec('php artisan ' . $command . ' ' . $_args, $output, $_returned);
        chdir($_cwd);

        return $_returned;
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
            $_linkTarget =
                'storage' == $_link
                    ? InstanceStorage::getStoragePath($this->instance)
                    : Disk::path([$_targetPath, $_link,]);

            if (false === symlink($_linkTarget, Disk::path([$_capsulePath, $_link]))) {
                $this->error('Error symlinking target "' . $_linkTarget . '"');
                $this->destroy();

                return false;
            }
        }

        //  Create an env
        if (!file_exists($_targetPath . '.env')) {
            $this->error('No .env file in instance path "' . $_targetPath . '"');
            $this->destroy();

            return false;
        }

        $_ini = Ini::make(Disk::path([$_targetPath, '.env']));

        //  Point to the database directly
        $_ini
            ->put('DB_DRIVER', 'mysql')
            ->put('DB_HOST', $this->instance->db_host_text)
            ->put('DB_DATABASE', $this->instance->db_name_text)
            ->put('DB_USERNAME', $this->instance->db_user_text)
            ->put('DB_PASSWORD', $this->instance->db_password_text)
            ->put('DB_PORT', $this->instance->db_port_nbr);

        $_targetEnv = Disk::path([$_capsulePath, '.env',]);

        if (false === $_ini->setFile($_targetEnv)->save()) {
            $this->error('Error saving capsule .env file "' . $_targetEnv . '".');
            $this->destroy();

            return false;
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

    /**
     * Destroy a capsule forcibly
     *
     * @return bool
     */
    protected function destroy()
    {
        try {
            $this->down(false);
        } catch (\Exception $_ex) {
            $this->error('Error removing capsule remnants.');

            return false;
        }

        return true;
    }
}
