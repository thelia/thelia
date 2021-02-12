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

namespace Thelia\Core\Stack;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\Event\SessionEvent;
use Thelia\Core\TheliaKernelEvents;

/**
 * Class SessionMiddleware.
 *
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
     * @param $cacheDir
     * @param $debug
     * @param $env
     *
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

                $this->eventDispatcher->dispatch($event, TheliaKernelEvents::SESSION);

                self::$session = $session = $event->getSession();
            }

            $session->start();
            $request->setSession($session);
        }

        return $this->app->handle($request, $type, $catch);
    }
}
