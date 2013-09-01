<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Thelia\Core\Factory\ActionEventFactory;
use Thelia\Core\Template\ParserContext;

/**
 *
 * Action are dispatched here.
 *
 * A factory is used for creating appropriate action object
 *
 * Class ControllerListener
 * @package Thelia\Core\EventListener
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ControllerListener implements EventSubscriberInterface
{
    /**
     * @var ParserContext the parser context
     */
    protected $parserContext;

    public function __construct(ParserContext $parserContext)
    {
        $this->parserContext = $parserContext;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $dispatcher = $event->getDispatcher();
        $request = $event->getRequest();

        if (false !== $action = $request->get("action")) {
           //search corresponding action
            $event = new ActionEventFactory($request, $action, $event->getKernel()->getContainer()->getParameter("thelia.actionEvent"));
            $actionEvent = $event->createActionEvent();
            $dispatcher->dispatch("action.".$action, $actionEvent);
         }
    }

   public static function getSubscribedEvents()
   {
        return array(
            KernelEvents::CONTROLLER => array('onKernelController', 0)
        );
   }
}
