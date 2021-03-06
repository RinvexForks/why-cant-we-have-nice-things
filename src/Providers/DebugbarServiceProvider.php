<?php

namespace History\Providers;

use Barryvdh\Debugbar\DataCollector\QueryCollector;
use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Twig_Environment;

class DebugbarServiceProvider extends AbstractServiceProvider
{
    /**
     * @var array
     */
    protected $provides = [
        StandardDebugBar::class,
        JavascriptRenderer::class,
    ];

    /**
     * Use the register method to register items with the container via the
     * protected $this->container property or the `getContainer` method
     * from the ContainerAwareTrait.
     */
    public function register()
    {
        $this->container->share(StandardDebugBar::class, function () {
            $twig = $this->container->get(Twig_Environment::class);

            // Create Debugbar
            $debugbar = new StandardDebugBar();
            $debugbar->addCollector(new QueryCollector());

            // Publish assets
            $renderer = $debugbar->getJavascriptRenderer();
            $renderer->dumpCssAssets('builds/debugbar.css');
            $renderer->dumpJsAssets('builds/debugbar.js');
            $renderer->addAssets(['/builds/debugbar.css'], ['/builds/debugbar.js']);

            // Bind renderer to views
            $twig->addGlobal('debugbar', $renderer);

            // Bind QueryCollector to current connection
            /* @var StandardDebugbar $debugbar */
            /* @var Connection $connection */
            $connection = $this->container->get(Manager::class)->connection();
            $connection->listen(function (QueryExecuted $event) use ($debugbar, $connection) {
                /** @var QueryCollector $collector */
                $collector = $debugbar->getCollector('queries');
                $collector->addQuery((string) $event->sql, $event->bindings, $event->time, $event->connection);
            });

            return $debugbar;
        });

        $this->container->share(JavascriptRenderer::class, function () {
            return $this->container->get(StandardDebugBar::class)->getJavascriptRenderer();
        });
    }
}
