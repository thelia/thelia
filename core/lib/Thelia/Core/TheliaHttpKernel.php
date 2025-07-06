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

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TheliaHttpKernel extends HttpKernel
{
    protected static $session;

    protected ContainerInterface $container;

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

    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param Request $request A Request instance
     * @param int     $type    The type of the request
     *                         (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param bool    $catch   Whether to catch exceptions or not
     *
     * @throws \Exception When an Exception occurs during processing
     *
     * @return Response A Response instance
     *
     * @api
     */
    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        $this->container->get('request.context')?->fromRequest($request);

        return parent::handle($request, $type, $catch);
    }
}
