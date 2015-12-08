<?php namespace DreamFactory\Enterprise\Instance\Capsule;

use DreamFactory\Enterprise\Instance\Capsule\Contracts\ProvidesCapsulePattern;
use DreamFactory\Library\Utility\Disk;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * An application capsule
 */
class Capsule
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Application The encapsulated application
     */
    protected $app;
    /**
     * @type Application The artisan instance of $app
     */
    protected $artisan;
    /**
     * @type string The application root
     */
    protected $basePath;
    /**
     * @type string The instance's id
     */
    protected $instanceId;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Constructor.
     *
     * @param string                 $instanceId The id of the instance
     * @param string                 $basePath   The application root path
     * @param ProvidesCapsulePattern $pattern    A capsule pattern
     */
    public function __construct($instanceId, $basePath, $pattern = null)
    {
        if (!is_dir($basePath) || !is_readable($basePath)) {
            throw new \InvalidArgumentException('The base-path "' . $basePath . '" does not exist or is not readable.');
        }

        if (!file_exists(Disk::path([$basePath, '.env']))) {
            throw new \InvalidArgumentException('No environment ".env" file found in base-path "' . $basePath . '".');
        }

        $this->instanceId = $instanceId;

        $this->bootstrap($basePath, $pattern);
    }

    /**
     * @param string                 $basePath The application base path
     * @param ProvidesCapsulePattern $pattern  The capsule pattern
     *
     * @return \Illuminate\Foundation\Application
     */
    protected function bootstrap($basePath, $pattern = null)
    {
        if ($pattern) {
            return $this->bootstrapPattern($basePath, $pattern);
        }

        //  Get the app's autoloader
        /** @noinspection PhpIncludeInspection */
        require(Disk::path([$basePath, 'bootstrap', 'autoload.php',]));

        /** @noinspection PhpIncludeInspection */
        $_app = require(Disk::path([$basePath, 'bootstrap', 'app.php',]));

        $this->basePath = $basePath;

        return $this->app = $_app;
    }

    /**
     * @param string                 $basePath The application base path
     * @param ProvidesCapsulePattern $pattern  The capsule pattern
     *
     * @return \Illuminate\Foundation\Application
     */
    protected function bootstrapPattern($basePath, $pattern)
    {
        //  Get the app's autoloader
        /** @noinspection PhpIncludeInspection */
        require(Disk::path([$basePath, 'vendor', 'autoload.php',]));

        //  Create the application
        $_app = new Application($basePath);
        $_app->useEnvironmentPath($basePath);

        foreach ($pattern as $_abstract => $_concrete) {
            $_app->singleton($_abstract, $_concrete);
        }

        //  Set up logging
        $_app->configureMonologUsing(function (Logger $monolog) {
            $_logFile = Disk::path([env('DFE_CAPSULE_LOG_PATH', storage_path('logs')), $this->instanceId . '.log',]);

            $_handler = new StreamHandler($_logFile);
            $_handler->setFormatter(new LineFormatter(null, null, true, true));

            $monolog->pushHandler($_handler);
        });

        $this->basePath = $basePath;

        return $this->app = $_app;
    }

    /**
     * Get the Artisan application instance.
     *
     * @return \Illuminate\Console\Application
     */
    public function getArtisan()
    {
        if (is_null($this->artisan)) {
            $this->artisan = $this->app->make('Illuminate\Contracts\Console\Kernel')->getArtisan();
        }

        return $this->artisan;
    }

    /**
     * @param string $command    The command to execute
     * @param array  $parameters The command parameters
     *
     * @return mixed
     */
    public function console($command, $parameters = [])
    {
        return $this->getArtisan()->call($command, $parameters);
    }

    /**
     * Returns the output of the prior command
     *
     * @return string
     */
    public function output()
    {
        return $this->getArtisan()->output();
    }

    /**
     * Process an HTTP request through the capsule's HTTP kernel
     */
    public function http()
    {
        /** @type Kernel $_kernel */
        $_kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');

        $_response = $_kernel->handle($_request = Request::capture());
        $_response->send();

        $_kernel->terminate($_request, $_response);
    }

    /**
     * @return Application
     */
    public function getApp()
    {
        return $this->app;
    }
}
