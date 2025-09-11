<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\HttpKernel;

/**
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TheliaHttpKernel extends HttpKernel
{
    protected ContainerInterface $container;

    public const IGNORE_THELIA_VIEW = 'ignore_thelia_view';

    public function __construct(
        EventDispatcherInterface $dispatcher,
        ContainerInterface $container,
        ControllerResolverInterface $controllerResolver,
        RequestStack $requestStack,
        ArgumentResolverInterface $argumentResolver,
    ) {
        parent::__construct($dispatcher, $controllerResolver, $requestStack, $argumentResolver);
        $container->get('thelia.url.manager');
        $this->container = $container;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
