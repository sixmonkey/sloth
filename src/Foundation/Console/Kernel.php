<?php

namespace Sloth\Foundation\Console;

use Illuminate\Console\Application as Console;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

class Kernel implements \Illuminate\Contracts\Console\Kernel
{
    /**
     * @var Application|\Sloth\Core\Application
     */
    protected $app;

    /**
     * The console application instance.
     *
     * @var \Illuminate\Console\Application
     */
    protected $console;

    /**
     * @var Dispatcher
     */
    protected $events;

    public function __construct(Application $app, Dispatcher $events)
    {
        if (! defined('ARTISAN_BINARY')) {
            define('ARTISAN_BINARY', 'console');
        }

        $this->app    = $app;
        $this->events = $events;
    }

    /**
     * @inheritDoc
     */
    public function all()
    {
        // TODO: Implement all() method.
    }

    /**
     * @inheritDoc
     */
    public function bootstrap()
    {
        // TODO: Implement bootstrap() method.
    }

    /**
     * @inheritDoc
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        // TODO: Implement call() method.
    }

    /**
     * @inheritDoc
     */
    public function handle($input, $output = null)
    {
        $this->bootstrap();

        return $this->getConsole()->run($input, $output);
    }

    /**
     * @inheritDoc
     */
    public function output()
    {
        // TODO: Implement output() method.
    }

    /**
     * @inheritDoc
     */
    public function queue($command, array $parameters = [])
    {
        // TODO: Implement queue() method.
    }

    /**
     * Alias. Set the console application instance.
     *
     * @param \Illuminate\Console\Application $artisan
     */
    public function setArtisan($artisan)
    {
        $this->setConsole($artisan);
    }

    /**
     * Set the console application instance.
     *
     * @param \Illuminate\Console\Application $console
     */
    public function setConsole($console)
    {
        $this->console = $console;
    }

    /**
     * @inheritDoc
     */
    public function terminate($input, $status)
    {
        // TODO: Implement terminate() method.
    }

    /**
     * Return the console application instance.
     *
     * @return \Illuminate\Console\Application
     */
    protected function getConsole()
    {
        if (is_null($this->console)) {
            $console = new Console($this->app, $this->events, $this->app->version());
            $console->setName('sloth');

            return $this->console = $console->resolveCommands($this->commands);
        }

        return $this->console;
    }

    /**
     * Register all of the commands in the given directory.
     *
     * @param array|string $paths
     */
    protected function load($paths)
    {
        $paths = array_unique(Arr::wrap($paths));

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return;
        }

        $namespace = $this->app->getNamespace();

        foreach ((new Finder)->in($paths)->files() as $command) {
            $command = $namespace . str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($command->getPathname(), realpath(app_path()) . DIRECTORY_SEPARATOR)
            );

            if (is_subclass_of($command, Command::class) &&
                ! (new ReflectionClass($command))->isAbstract()) {
                Artisan::starting(function ($artisan) use ($command) {
                    $artisan->resolve($command);
                });
            }
        }
    }
}
