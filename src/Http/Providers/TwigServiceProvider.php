<?php

namespace History\Http\Providers;

use History\Application;
use History\Entities\Models\Question;
use History\Entities\Models\Vote;
use History\Services\UrlGenerator;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Message\ServerRequestInterface;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;
use Twig_SimpleFunction;

class TwigServiceProvider extends AbstractServiceProvider
{
    /**
     * @var int
     */
    const PRECISION = 1;

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
        $this->container->share(Twig_Environment::class, function () {
            $loader = new Twig_Loader_Filesystem($this->container->get('paths.views'));
            $debug = $this->container->get('debug');
            $twig = new Twig_Environment($loader, [
                'debug' => $debug,
                'auto_reload' => $debug,
                'strict_variables' => false,
                'cache' => $this->container->get('paths.cache').'/twig',
            ]);

            // Configure Twig
            $this->registerGlobalVariables($twig);
            $this->addTwigExtensions($twig);

            return $twig;
        });
    }

    /**
     * Add extensions to Twig.
     *
     * @param Twig_Environment $twig
     */
    private function addTwigExtensions(Twig_Environment $twig)
    {
        $twig->addExtension(new Twig_Extension_Debug());

        $twig->addFunction(new Twig_SimpleFunction('url', function ($action, $parameters = []) {
            return $this->container->get(UrlGenerator::class)->to($action, $parameters);
        }));

        $twig->addFunction(new Twig_SimpleFunction('percentage', function ($number) {
            return round($number * 100, self::PRECISION).'%';
        }));

        $twig->addFunction(new Twig_SimpleFunction('choice', function (Question $question, Vote $vote) {
            return ucfirst($question->choices[$vote->choice - 1]);
        }));
    }

    /**
     * Register global variables with Twig.
     *
     * @param Twig_Environment $twig
     */
    private function registerGlobalVariables(Twig_Environment $twig)
    {
        $url = $this->container->get(UrlGenerator::class);

        $twig->addGlobal('app_name', Application::NAME);

        /** @var ServerRequestInterface $request */
        $request = $this->container->get(ServerRequestInterface::class);
        $uri = $request->getUri()->getPath().'?'.urldecode($request->getUri()->getQuery());
        $twig->addGlobal('current_uri', $uri);
        $twig->addGlobal('precision', self::PRECISION);
        $twig->addGlobal('assets', $this->getWebpackAssets());

        $twig->addGlobal('navigation', [
            ['key' => 'users', 'uri' => $url->to('users.index'), 'label' => 'Users'],
            ['key' => 'events', 'uri' => $url->to('events.index'), 'label' => 'Timeline'],
            ['key' => 'requests', 'uri' => $url->to('requests.index'), 'label' => 'RFCs'],
            ['key' => 'groups', 'uri' => $url->to('groups.index'), 'label' => 'Mailing list'],
            ['key' => 'companies', 'uri' => $url->to('companies.index'), 'label' => 'Companies'],
            ['key' => 'pages', 'uri' => $url->to('pages.about'), 'label' => 'About'],
        ]);
    }

    /**
     * Bind the path to the Webpack assets to the views.
     *
     * @return array
     */
    private function getWebpackAssets()
    {
        $assets = $this->container->get('paths.builds').'/manifest.json';
        if (!file_exists($assets)) {
            return [];
        }

        $assets = file_get_contents($assets);
        $assets = json_decode($assets, true);

        return $assets;
    }
}
