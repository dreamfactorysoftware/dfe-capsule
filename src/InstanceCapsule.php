<?php namespace DreamFactory\Enterprise\Instance\Capsule;

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
     * @type Capsule The instance capsule
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
    /**
     * @type bool If true, encapsulated instances are destroyed when this class does
     */
    protected $selfDestruct = true;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Instantiate and bring up an encapsulated instance
     *
     * @param string|Instance $instance
     * @param bool            $selfDestruct If true, encapsulated instances are destroyed when this class does
     *
     * @return static
     */
    public static function make($instance, $selfDestruct = true)
    {
        $_capsule = new static($instance, $selfDestruct);
        $_capsule->up();

        return $_capsule;
    }

    /**
     * Instantiate and bring up an encapsulated instance
     *
     * @param string|Instance $instance
     */
    public static function unmake($instance)
    {
        $_capsule = null;

        try {
            $_capsule = new static($instance, true);
            $_capsule->up();
        } catch (\Exception $_ex) {
            //  Ignored
        }
        finally {
            $_capsule && $_capsule->destroy();
        }
    }

    /**
     * Capsule constructor.
     *
     * @param Instance|string $instance        The instance or instance-id to encapsulate
     * @param string|null     $capsuleRootPath The root path of all capsules
     * @param bool            $selfDestruct    If true, encapsulated instances are destroyed when this class does
     */
    public function __construct($instance, $selfDestruct = true, $capsuleRootPath = null)
    {
        $this->selfDestruct = $selfDestruct;
        $this->capsuleRootPath = $capsuleRootPath ?: config('capsule.root-path', CapsuleDefaults::DEFAULT_PATH);

        if (!is_dir($this->capsuleRootPath) && !Disk::ensurePath($this->capsuleRootPath)) {
            throw new \RuntimeException('Cannot create, or write to, capsule.root-path "' .
                $this->capsuleRootPath .
                '".');
        }

        $this->instance = $this->findInstance($instance);
        $this->id = sha1($this->instance->cluster->cluster_id_text . '.' . $this->instance->instance_id_text);
    }

    /**
     * Initiate self-destruct sequence
     */
    public function __destruct()
    {
        try {
            if ($this->selfDestruct) {
                $this->destroy();
            }
        } catch (\Exception $_ex) {
        }
    }

    /**
     * Boot up the capsule
     *
     * @return InstanceCapsule
     */
    public function up()
    {
        if (!$this->encapsulate()) {
            throw new \RuntimeException('The instance failed to boot.');
        }

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
        if (!$this->capsule) {
            return false;
        }

        return $this->capsule->console($command, $arguments);
//
//        $_args = [];
//
//        foreach ($arguments as $_key => $_value) {
//            $_segment = $_key;
//
//            if (!empty($_value)) {
//                $_segment .= (('--' == substr($_key, 0, 2)) ? '=' : ' ') . escapeshellarg($_value);
//            }
//
//            $_args[] = $_segment;
//        }
//
//        //  Build a command...
//        $_pid = new Process('php artisan ' . $command . ' ' . implode(' ', $_args), $this->capsulePath);
//
//        $_pid->run(function ($type, $buffer) use ($output) {
//            $output = trim($buffer);
//        });
//
//        return $_pid->getExitCode();
    }

    /**
     * Shut down capsule
     *
     * @param bool $keep If true, the instance capsule will not be removed.
     */
    public function down($keep = false)
    {
        //  A keeper or no id? No way!
        if ($keep || !$this->capsulePath) {
            return;
        }

        if (0 != `rm -rf $this->capsulePath`) {
            throw new \RuntimeException('Unable to remove capsule path "' . $this->capsulePath . '".');
        }

        $this->capsule = null;
        $this->capsulePath = null;
    }

    /**
     * Create the necessary links to run the instance
     *
     * @return bool
     */
    protected function encapsulate()
    {
        $_storageRoot = config('provisioning.storage-root', storage_path());
        $_capsulePath = Disk::path([$this->capsuleRootPath, $this->id], true);
        $_targetPath = config('capsule.instance.install-path', CapsuleDefaults::DEFAULT_INSTANCE_INSTALL_PATH);
        $_links = config('capsule.instance.symlinks', []);

        //  Create symlinks
        foreach ($_links as $_link) {
            $_linkTarget =
                'storage' == $_link
                    ? Disk::path([$_storageRoot, InstanceStorage::getStoragePath($this->instance)])
                    : Disk::path([$_targetPath, $_link,]);

            $_linkName = Disk::path([$_capsulePath, $_link]);

            if (!file_exists($_linkName) || $_linkTarget != readlink($_linkName)) {
                if (false === symlink($_linkTarget, $_linkName)) {
                    $this->error('Error symlinking target "' . $_linkTarget . '"');
                    $this->destroy();

                    return false;
                }
            }
        }

        //  Create an env
        if (!file_exists(Disk::path([$_targetPath, '.env']))) {
            $this->error('No .env file in instance path "' . $_targetPath . '"');
            $this->destroy();

            return false;
        }

        $_ini = Ini::makeFromFile(Disk::path([$_targetPath, '.env']));

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
        $this->capsule = new Capsule($this->instance->instance_id_text, $this->capsulePath);

        return true;
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

    /**
     * @return string
     */
    public function getCapsulePath()
    {
        return $this->capsulePath;
    }

    /**
     * @return Capsule
     */
    public function getCapsule()
    {
        return $this->capsule;
    }

    /**
     * @return Instance
     */
    public function getInstance()
    {
        return $this->instance;
    }
}
