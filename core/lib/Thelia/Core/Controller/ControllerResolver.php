<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ControllerResolver extends ContainerControllerResolver
{
    protected function instantiateController($class): object
    {
        return $this->configureController(parent::instantiateController($class), $class);
    }

    private function configureController($controller, string $class): object
    {
        if ($controller instanceof AbstractController) {
            if (null === $previousContainer = $controller->setContainer($this->container)) {
                throw new \LogicException(sprintf('"%s" has no container set, did you forget to define it as a service subscriber?', $class));
            }
            $controller->setContainer($previousContainer);
        }

        return $controller;
    }

    /**
     * Returns a callable for the given controller.
     *
     * @throws \LogicException           When the name could not be parsed
     * @throws \InvalidArgumentException When the controller class does not exist
     *
     * @return mixed A PHP callable
     */
    protected function createController(string $controller): callable
    {
        $controller = parent::createController($controller);

        // Additional treatment for thelia legacy controllers
        if (\is_array($controller) && isset($controller[0])) {
            $controllerinstance = $controller[0];

            if (method_exists($controllerinstance, 'getControllerType')) {
                $this->container->get('request_stack')->getCurrentRequest()->setControllerType(
                    $controllerinstance->getControllerType()
                );
            }
        }

        return $controller;
    }
}
