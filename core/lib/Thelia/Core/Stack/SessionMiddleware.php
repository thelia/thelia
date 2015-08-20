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

namespace Thelia\Core\Stack;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\TheliaKernelEvents;

/**
 * Class SessionMiddleware
 * @package Thelia\Core\Stack
 * @author manuel raynaud <manu@raynaud.io>
 */
class SessionMiddleware implements HttpKernelInterface
{
    /**
     * @var HttpKernelInterface
     */
    protected $app;

    protected $eventDispatcher;

    protected $cacheDir;

    protected $env;

    protected $debug;

    protected static $session;

    /**
     * @param HttpKernelInterface $app
     * @param EventDispatcherInterface $eventDispatcherInterface
     * @param $cacheDir
     * @param $debug
     * @param $env
     * @internal param ContainerInterface $container
     */
    public function __construct(HttpKernelInterface $app, EventDispatcherInterface $eventDispatcherInterface, $cacheDir, $debug, $env)
    {
        $this->app = $app;
        $this->eventDispatcher = $eventDispatcherInterface;
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;
        $this->env = $env;
    }

    /*
     * @inherited
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        if ($type == HttpKernelInterface::MASTER_REQUEST) {
            if (null === $session = self::$session) {
                $event = new SessionEvent($this->cacheDir, $this->debug, $this->env);

                $this->eventDispatcher->dispatch(TheliaKernelEvents::SESSION, $event);

                self::$session = $session = $event->getSession();
            }

            $session->start();
            $request->setSession($session);
        }

        return $this->app->handle($request, $type, $catch);
    }
}
