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

namespace Thelia\Core;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model;

/**
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */

class TheliaHttpKernel extends HttpKernel
{
    protected static $session;

    protected $container;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        ContainerInterface $container,
        ControllerResolverInterface $controllerResolver,
        RequestStack $requestStack
    ) {
        parent::__construct($dispatcher, $controllerResolver, $requestStack);

        $this->container = $container;
    }

    public function getContainer()
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
     * @param integer $type    The type of the request
     *                         (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param Boolean $catch   Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     *
     * @api
     *
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        // for backward compatibility
        $this->container->set('request', $request);
        $this->container->get('request.context')->fromRequest($request);

        $response = parent::handle($request, $type, $catch);

        return $response;
    }
}
