<?php
namespace History\Providers;

use League\Container\ServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

class TwigServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $provides = [
        Twig_Environment::class,
    ];

    /**
     * Use the register method to register items with the container via the
     * protected $this->container property or the `getContainer` method
     * from the ContainerAwareTrait.
     */
    public function register()
    {
        $this->container->singleton(Twig_Environment::class, function () {
            $loader = new Twig_Loader_Filesystem(__DIR__.'/../../resources/views');
            $twig = new Twig_Environment($loader, [
                'auto_reload'      => getenv('APP_ENV') === 'local',
                'strict_variables' => false,
                'cache'            => __DIR__.'/../../cache',
            ]);

            $request = $this->container->get(Request::class);
            $twig->addGlobal('current_uri', $request->getPathInfo());
            $twig->addGlobal('precision', 1);

            $twig->addExtension(new Twig_Extension_Debug());

            return $twig;
        });
    }
}
