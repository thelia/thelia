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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Thelia\Core\Event\Currency\CurrencyChangeEvent;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model;

/**
 *
 * @author Manuel Raynaud <manu@thelia.net>
 */

class TheliaHttpKernel extends HttpKernel
{
    protected static $session;

    protected $container;

    public function __construct(EventDispatcherInterface $dispatcher, ContainerInterface $container, ControllerResolverInterface $controllerResolver)
    {
        parent::__construct($dispatcher, $controllerResolver);

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
        $this->container->enterScope('request');
        $this->container->set('request', $request, 'request');

        try {
            $response = parent::handle($request, $type, $catch);
        } catch (\Exception $e) {
            $this->container->leaveScope('request');

            throw $e;
        }

        $this->container->leaveScope('request');

        return $response;
    }
}
