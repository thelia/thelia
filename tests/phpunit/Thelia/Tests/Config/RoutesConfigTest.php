<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Config;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;
use Thelia\Core\Controller\ControllerResolver;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Tests\ContainerAwareTestCase;
use TheliaSmarty\Template\SmartyParser;

/**
 * Check the core and front routing files.
 *
 * @author Baptiste Cabarrou <bcabarrou@openstudio.fr>
 */
class RoutesConfigTest extends ContainerAwareTestCase
{
    /**
     * Path to the routing files XSD.
     * @var string
     */
    protected static $routingXSDPath;

    /**
     * Path to the core (admin + api) routing files.
     * @var string
     */
    protected static $routingFilesPathCore;

    /**
     * Path to the front routing files.
     * @var string
     */
    protected static $routingFilesPathFront;

    /**
     * Routing files paths => [routing files names].
     * @var array
     */
    protected static $routingFiles;

    /**
     * Routing files paths => file locator for that path.
     * @var FileLocatorInterface[]
     */
    protected $routerFileLocators;

    /**
     * Routing files paths => file loader for that path.
     * @var LoaderInterface[]
     */
    protected $routerFileLoaders;

    protected function buildContainer(ContainerBuilder $container)
    {
    }

    public static function setUpBeforeClass()
    {
        self::$routingXSDPath
            = THELIA_VENDOR
            . DS . 'symfony'
            . DS . 'routing'
            . DS . 'Loader'
            . DS . XmlFileLoader::SCHEME_PATH;

        // core files
        self::$routingFilesPathCore
            = THELIA_LIB
            . DS . 'Config'
            . DS . 'Resources'
            . DS . 'routing';

        self::$routingFiles[self::$routingFilesPathCore] = [
            'admin.xml',
            'api.xml',
        ];

        // front files
        self::$routingFilesPathFront
            = THELIA_MODULE_DIR
            . DS . 'Front'
            . DS . 'Config';

        self::$routingFiles[self::$routingFilesPathFront] = [
            'front.xml'
        ];
    }

    public function setUp()
    {
        foreach (static::$routingFiles as $filePath => $fileNames) {
            $this->routerFileLocators[$filePath] = new FileLocator($filePath);
            $this->routerFileLoaders[$filePath] = new XmlFileLoader($this->routerFileLocators[$filePath]);
        }
    }

    /**
     * Check that there are no duplicate route ids.
     */
    public function testNoDuplicateIds()
    {
        $existingIds = [];

        foreach (static::$routingFiles as $filePath => $fileNames) {
            $routerFileLocator = $this->routerFileLocators[$filePath];

            foreach ($fileNames as $fileName) {
                $xml = XmlUtils::loadFile(
                    $routerFileLocator->locate($fileName),
                    static::$routingXSDPath
                );

                foreach ($xml->documentElement->childNodes as $node) {
                    if (!$node instanceof \DOMElement) {
                        continue;
                    }

                    if ($node->localName != 'route') {
                        continue;
                    }

                    $id = $node->getAttribute('id');

                    $this->assertFalse(in_array($id, $existingIds), "Duplicate route id '$id'.");

                    $existingIds[] = $id;
                }
            }
        }
    }

    /**
     * Check that there are no obvious duplicate route methods + paths.
     * Will not catch all duplicates, but should catch most of the common errors.
     * Can catch some routes that looks like they are conflicting but are not due to disjoint arguments requirements.
     */
    public function testNoDuplicatePaths()
    {
        // a map of existing paths => [existing methods for this path]
        $existingPaths = [];

        foreach (static::$routingFiles as $filePath => $fileNames) {
            $routerFileLoader = $this->routerFileLoaders[$filePath];

            foreach ($fileNames as $fileName) {
                $router = new Router($routerFileLoader, $fileName);

                /** @var Route $route */
                foreach ($router->getRouteCollection() as $route) {
                    $routeMethods = $route->getMethods();
                    if (empty($routeMethods)) {
                        $routeMethods = ['*'];
                    }

                    // check for path collision, then method collision
                    $pathsCollide = in_array($route->getPath(), array_keys($existingPaths));

                    if ($pathsCollide) {
                        $methodsIntersection = array_intersect($routeMethods, $existingPaths[$route->getPath()]);
                    } else {
                        $methodsIntersection = [];
                    }

                    $methodsCollide = !empty($methodsIntersection);

                    $this->assertFalse(
                        $pathsCollide && $methodsCollide,
                        "Potentially duplicate route path '"
                        . implode('|', $methodsIntersection)
                        . " "
                        . $route->getPath()
                        . "'."
                    );

                    if ($pathsCollide) {
                        $existingPaths[$route->getPath()]
                            = array_merge($existingPaths[$route->getPath()], $routeMethods);
                    } else {
                        $existingPaths[$route->getPath()] = $routeMethods;
                    }
                }
            }
        }
    }

    /**
     * Check that controller methods for the routes are callable.
     */
    public function testTargetControllerMethodsAreCallable()
    {
        $controllerResolver = new ControllerResolver($this->getContainer());

        foreach (static::$routingFiles as $filePath => $fileNames) {
            $routerFileLoader = $this->routerFileLoaders[$filePath];

            foreach ($fileNames as $fileName) {
                $router = new Router($routerFileLoader, $fileName);

                /** @var Route $route */
                foreach ($router->getRouteCollection() as $route) {
                    // prepare a dummy request to the controller so that we can check it using the ControllerResolver
                    $request = new Request();
                    $request->attributes->set('_controller', $route->getDefault('_controller'));

                    // will throw an exception if the controller method is not callable
                    $controllerResolver->getController($request);
                }
            }
        }
    }

    /**
     * Check that views for the front routes exists.
     */
    public function testTargetFrontViewsExists()
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->getContainer()->get('request_stack');

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        // we are not going to do any actual rendering, so a mock ParserContext should be enough
        /** @var ParserContext $parserContext */
        $parserContext = $this
            ->getMockBuilder('Thelia\Core\Template\ParserContext')
            ->disableOriginalConstructor()
            ->getMock();

        $templateHelper = new TheliaTemplateHelper();

        $parser = new SmartyParser(
            $requestStack,
            $eventDispatcher,
            $parserContext,
            $templateHelper
        );
        $parser->setTemplateDefinition($templateHelper->getActiveFrontTemplate());

        $frontRouterFileLoader = $this->routerFileLoaders[static::$routingFilesPathFront];

        foreach (static::$routingFiles[static::$routingFilesPathFront] as $fileName) {
            $router = new Router($frontRouterFileLoader, $fileName);

            /** @var Route $route */
            foreach ($router->getRouteCollection() as $route) {
                if (null === $view = $route->getDefault('_view')) {
                    continue;
                }

                $this->assertTrue(
                    $parser->templateExists($view . '.html'),
                    "Front view '$view' does not exist."
                );
            }
        }
    }
}
