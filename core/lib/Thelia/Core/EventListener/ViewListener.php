<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserInterface;

/**
 *
 * ViewSubscriber Main class subscribing to view http response.
 *
 * @TODO Look if it's possible to block this definition in dependency-injection
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class ViewListener implements EventSubscriberInterface
{
    /**
     *
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     *
     * @param \Thelia\Core\Template\ParserInterface $parser
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     *
     * Launch the parser defined on the constructor and get the result.
     *
     * The result is transform id needed into a Response object
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {

        $parser = $this->container->get('thelia.parser');

        try {
            $content = $parser->getContent();

            if ($content instanceof Response) {
                $event->setResponse($content);
            } else {
                $event->setResponse(new Response($content, $parser->getStatus() ?: 200));
            }
        } catch(ResourceNotFoundException $e) {
            $event->setResponse(new Response($e->getMessage(), 404));
        }

    }

    public function beforeKernelView(GetResponseForControllerResultEvent $event)
    {
        $request = $this->container->get('request');

        if (!$view = $request->attributes->get('_view')) {
            $request->attributes->set('_view', $this->findView($request));
        }

    }

    public function findView(Request $request)
    {
        if (! $view = $request->query->get('view')) {
            $view = "index";
            if ($request->request->has('view')) {
                $view = $request->request->get('view');
            }
        }

        return $view;
    }


    /**
     *
     * Register the method to execute in this class for a specific event (here the kernel.view event)
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::VIEW =>array(
                array('onKernelView', 0),
                array('beforeKernelView', 5)
            )
        );
    }
}
