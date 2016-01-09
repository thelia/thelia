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

namespace Thelia\Core\Controller;

use Symfony\Component\HttpKernel\Controller\ControllerResolver as BaseControllerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Thelia\Controller\BaseController;

/**
 * ControllerResolver that supports "a:b:c", "service:method" and class::method" notations in routes definition
 * thus allowing the definition of controllers as service (see http://symfony.com/fr/doc/current/cookbook/controller/service.html)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ControllerResolver extends BaseControllerResolver
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param LoggerInterface    $logger    A LoggerInterface instance
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->container = $container;

        parent::__construct($logger);
    }

    /**
     * Returns a callable for the given controller.
     *
     * @param string $controller A Controller string
     *
     * @return mixed A PHP callable
     *
     * @throws \LogicException           When the name could not be parsed
     * @throws \InvalidArgumentException When the controller class does not exist
     */
    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            $count = substr_count($controller, ':');
            if (2 == $count) {
                // controller in the a:b:c notation then
                list($moduleName, $controllerName, $method) = explode(':', $controller, 3);
                $class = $moduleName . '\\Controller\\' . $controllerName . 'Controller';
                $method .= 'Action';
            } elseif (1 == $count) {
                // controller in the service:method notation
                list($service, $method) = explode(':', $controller, 2);

                return array($this->container->get($service), $method);
            } else {
                throw new \LogicException(sprintf('Unable to parse the controller name "%s".', $controller));
            }
        } else {
            list($class, $method) = explode('::', $controller, 2);
        }

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        /** @var BaseController $controller */
        $controller = new $class();

        $this->container->get('request')->setControllerType(
            $controller->getControllerType()
        );

        if (method_exists($controller, 'setContainer')) {
            $controller->setContainer($this->container);
        }

        return array($controller, $method);
    }
}
