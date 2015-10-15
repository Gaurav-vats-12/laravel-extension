<?php

namespace LaravelPlus\Extension;

use Illuminate\Support\Facades\Blade;
use LaravelPlus\Extension\Addons\Addon;
use LaravelPlus\Extension\Addons\AddonClassLoader;
use LaravelPlus\Extension\Addons\AddonGenerator;
use LaravelPlus\Extension\Templates\BladeExtension;
use Jumilla\Versionia\Laravel\Migrator;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * @var array
     */
    protected static $commands = [
// app:
        'command+.app.container' => Console\AppContainerCommand::class,
        'command+.app.route' => Console\RouteListCommand::class,
        'command+.app.tail' => Console\TailCommand::class,
// addon:
        'command+.addon.status' => Addons\Console\AddonStatusCommand::class,
        'command+.addon.make' => Addons\Console\AddonMakeCommand::class,
        'command+.addon.name' => Addons\Console\AddonNameCommand::class,
        'command+.addon.remove' => Addons\Console\AddonRemoveCommand::class,
        'command+.addon.check' => Addons\Console\AddonCheckCommand::class,
// database:
        'command+.database.status' => Database\Console\DatabaseStatusCommand::class,
        'command+.database.upgrade' => Database\Console\DatabaseUpgradeCommand::class,
        'command+.database.clean' => Database\Console\DatabaseCleanCommand::class,
        'command+.database.refresh' => Database\Console\DatabaseRefreshCommand::class,
        'command+.database.rollback' => Database\Console\DatabaseRollbackCommand::class,
        'command+.database.again' => Database\Console\DatabaseAgainCommand::class,
        'command+.database.seed' => Database\Console\DatabaseSeedCommand::class,
// hash:
        'command+.hash.make' => Console\HashMakeCommand::class,
        'command+.hash.check' => Console\HashCheckCommand::class,
    ];

    /**
     * @var array
     */
    protected $addons;

    /**
     * Register the service provider.
     */
    public function register()
    {
        $app = $this->app;

        // register spec path for app
        $app['path.specs'] = $app->basePath().'/resources/specs';

        // register spec repository
        $app->singleton('specs', function ($app) {
            $loader = new Repository\FileLoader($app['files'], $app['path.specs']);

            return new Repository\NamespacedRepository($loader);
        });

        // register addon generator
        $app->singleton('addons.generator', function ($app) {
            return new AddonGenerator();
        });
        $app->alias('addons.generator', AddonGenerator::class);

        // register database migrator
        $app->singleton('database.migrator', function ($app) {
            return new Migrator($app['db']);
        });
        $app->alias('database.migrator', Migrator::class);

        $this->registerClassResolvers();

        $this->setupPackageCommands(static::$commands);

        $this->registerAddons();
    }

    /**
     */
    protected function registerClassResolvers()
    {
        AddonClassLoader::register(Application::getAddons());

        AliasResolver::register(Application::getAddons(), $this->app['config']->get('app.aliases'));
    }

    /**
     * setup package's commands.
     *
     * @param array $commands
     */
    protected function setupPackageCommands(array $commands)
    {
        foreach ($commands as $name => $class) {
            $this->app->singleton($name, function ($app) use ($class) {
                return $app->build($class);
            });
        }

        // Now register all the commands
        $this->commands(array_keys($commands));
    }

    /**
     */
    protected function registerAddons()
    {
        foreach (Application::getAddons() as $addon) {
            // register addon
            $addon->register($this->app);
        }
    }

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        //
        $this->registerBladeExtensions();

        // setup all addons
        $this->bootAddons();
    }

    /**
     * register blade extensions.
     */
    protected function registerBladeExtensions()
    {
        Blade::extend(BladeExtension::comment());

        Blade::extend(BladeExtension::script());
    }

    /**
     * setup & boot addons.
     */
    protected function bootAddons()
    {
        foreach (Application::getAddons() as $name => $addon) {
            // register package
            $this->registerPackage($name, $addon);

            // boot addon
            $addon->boot($this->app);
        }
    }

    /**
     * Register the package's component namespaces.
     *
     * @param string                              $namespace
     * @param \LaravelPlus\Extension\Addons\Addon $addon
     */
    protected function registerPackage($namespace, $addon)
    {
        $lang = $addon->path($addon->config('addon.paths.lang', 'lang'));
        if (is_dir($lang)) {
            $this->app['translator']->addNamespace($namespace, $lang);
        }

        $view = $addon->path($addon->config('addon.paths.views', 'views'));
        if (is_dir($view)) {
            $this->app['view']->addNamespace($namespace, $view);
        }

        $spec = $addon->path($addon->config('addon.paths.specs', 'specs'));
        if (is_dir($spec)) {
            $this->app['specs']->addNamespace($namespace, $spec);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_keys(static::$commands);
    }
}
