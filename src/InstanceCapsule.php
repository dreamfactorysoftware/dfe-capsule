<?php namespace DreamFactory\Enterprise\Instance\Capsule;

use DreamFactory\Enterprise\Common\Traits\EntityLookup;
use DreamFactory\Enterprise\Common\Traits\Lumberjack;
use DreamFactory\Enterprise\Common\Utility\Ini;
use DreamFactory\Enterprise\Database\Models\Instance;
use DreamFactory\Enterprise\Instance\Capsule\Enums\CapsuleDefaults;
use DreamFactory\Enterprise\Storage\Facades\InstanceStorage;
use DreamFactory\Library\Utility\Disk;
use DreamFactory\Library\Utility\Enums\GlobFlags;

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
        try {
            $_capsule = new static($instance, true);
        } catch (\Exception $_ex) {
            //  Ignored
        }
        finally {
            isset($_capsule) && $_capsule->destroy();
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
            throw new \RuntimeException('Cannot create, or write to, capsule.root-path "' . $this->capsuleRootPath . '".');
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
     *
     * @return int|bool The return value of the call or false on failure
     */
    public function call($command, $arguments = [])
    {
        if (!$this->capsulePath) {
            throw new \LogicException('No capsule loaded. Cannot run command "' . $command . '".');
        }

        return $this->getCapsule()->console($command, $arguments);
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
        //  A keeper or no id or bogus path? No way!
        if ($keep || !$this->capsulePath || DIRECTORY_SEPARATOR == $this->capsulePath) {
            return;
        }

        if (0 != `rm -rf $this->capsulePath`) {
            throw new \RuntimeException('Unable to remove capsule path "' . $this->capsulePath . '".');
        }

        $this->capsule = null;
        $this->capsulePath = null;
    }

    /**
     * Create the necessary links to run the instance but does not instantiate!
     *
     * @return bool
     */
    protected function encapsulate()
    {
        try {
            $storage = config('provisioning.storage-root', storage_path());
            $_capsulePath = $this->makeCapsulePath(true);
            $_targetPath = config('capsule.instance.install-path', CapsuleDefaults::DEFAULT_INSTANCE_INSTALL_PATH);

            //  No .env file? No go!
            if (!file_exists(Disk::path([$_targetPath, '.env']))) {
                $this->error('No .env file in instance path "' . $_targetPath . '"');
                $this->destroy();

                return false;
            }

            //  Use symlinks?
            if (config('capsule.use-symlinks', false)) {
                $this->createCapsuleLinks($_targetPath, $_capsulePath, $storage);
            } else {
                //  Copy files...
                $_command = 'cp -r ' . $_targetPath . DIRECTORY_SEPARATOR . '* ' . $_capsulePath . DIRECTORY_SEPARATOR;
                exec($_command, $_output, $_return);

                if (0 !== $_return) {
                    $this->error('Error (' . $_return . ') while copying source "' . $_targetPath . '" to "' . $_capsulePath . '".');
                    $this->error(implode(PHP_EOL, $_output));

                    $this->destroy();

                    return false;
                }

                //  Symlink REAL storage directory for this instance
                if (!is_link($_linkName = Disk::path([$_capsulePath, 'storage']))) {
                    //  Remove and link the storage directory...
                    $_command = 'rm -rf ' . ($_destinationStorage = Disk::path([$_capsulePath, 'storage',]));
                    exec($_command, $_output, $_return);

                    if (0 !== $_return) {
                        $this->error('Error (' . $_return . ') while removing source storage directory "' . $_destinationStorage . '".');
                        $this->error(implode(PHP_EOL, $_output));

                        $this->destroy();

                        return false;
                    }

                    $_linkTarget = Disk::path([$storage, InstanceStorage::getStoragePath($this->instance)]);

                    if (false === symlink($_linkTarget, $_linkName)) {
                        $this->error('Error symlinking target storage directory "' . $_linkTarget . '"');
                        $this->destroy();

                        return false;
                    }
                }
            }

            //  Create an env
            $_ini = Ini::makeFromFile(Disk::path([$_targetPath, '.env']));

            //  Point to the database directly
            $_ini->put('DB_DRIVER', 'mysql')
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
        } catch (\Exception $_ex) {
            $this->error('Exception: ' . $_ex->getMessage());
            $this->destroy();

            return false;
        }
    }

    /**
     * Create capsule symlinks
     *
     * @param string $source      The instance source
     * @param string $destination The destination
     * @param string $storage     The storage path to link
     *
     * @return bool
     */
    protected function createCapsuleLinks($source, $destination, $storage)
    {
        $_links = config('capsule.instance.symlinks', []);

        //  Create symlinks
        foreach ($_links as $_link) {
            $_linkTarget = 'storage' == $_link
                ? Disk::path([$storage, InstanceStorage::getStoragePath($this->instance)])
                : Disk::path([
                    $source,
                    $_link,
                ]);

            $_linkName = Disk::path([$destination, $_link]);

            if (!file_exists($_linkName) || $_linkTarget != readlink($_linkName)) {
                if (false === symlink($_linkTarget, $_linkName)) {
                    $this->error('Error symlinking target "' . $_linkTarget . '"');
                    $this->destroy();

                    return false;
                }
            }
        }

        //  Create the bootstrap[/cache] directory
        $_sourcePath = Disk::path([$source, 'bootstrap']);
        $_bootstrapPath = Disk::path([$destination, 'bootstrap'], true);

        //  Ensure the cache directory is there as well...
        Disk::path([$_bootstrapPath, 'cache'], true);

        $_files = Disk::glob($_sourcePath . DIRECTORY_SEPARATOR . '*', GlobFlags::GLOB_NODIR | GlobFlags::GLOB_NODOTS);

        foreach ($_files as $_file) {
            if (false === copy($_sourcePath . DIRECTORY_SEPARATOR . $_file, $_bootstrapPath . DIRECTORY_SEPARATOR . $_file)) {
                $this->error('Failure copying bootstrap file "' . $_file . '" to "' . $_bootstrapPath . '"');
                $this->destroy();

                return false;
            }
        }

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
            !$this->capsulePath && $this->capsulePath = $this->makeCapsulePath();
            $this->down(false);
        } catch (\Exception $_ex) {
            $this->error('Error removing capsule remnants.');

            return false;
        }

        return true;
    }

    /**
     * @return string The current capsule path, if any
     */
    public function getCapsulePath()
    {
        return $this->capsulePath;
    }

    /**
     * Generates a capsule path optionally ensuring
     *
     * @param bool $create
     * @param int  $mode
     * @param bool $recursive
     *
     * @return string
     */
    public function makeCapsulePath($create = false, $mode = 0777, $recursive = true)
    {
        return Disk::path([$this->capsuleRootPath, $this->id], $create, $mode, $recursive);
    }

    /**
     * @return Capsule|null
     */
    public function getCapsule()
    {
        if (!$this->capsulePath) {
            return null;
        }

        if (!$this->capsule) {
            $this->capsule = new Capsule($this->instance->instance_id_text, $this->capsulePath);
        }

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
