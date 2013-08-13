<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Thelia\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\URL;
use Thelia\Tools\Redirect;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Event\ActionEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Core\Factory\ActionEventFactory;

/**
 *
 * The defaut administration controller. Basically, display the login form if
 * user is not yet logged in, or back-office home page if the user is logged in.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class BaseController extends ContainerAware
{

    /**
     * Return an empty response (after an ajax request, for example)
     */
    protected function nullResponse()
    {
        return new Response();
    }

    /**
     * Create an action event
     *
     * @param string $action
     * @return EventDispatcher
     */
    protected function dispatchEvent($action)
    {
        // Create the
        $eventFactory = new ActionEventFactory($this->getRequest(), $action, $this->container->getParameter("thelia.actionEvent"));

        $actionEvent = $eventFactory->createActionEvent();

        $this->dispatch("action.$action", $actionEvent);

        if ($actionEvent->hasErrorForm()) {
            $this->getParserContext()->setErrorForm($actionEvent->getErrorForm());
        }

        return $actionEvent;
    }

    /**
     * Dispatch a Thelia event to modules
     *
     * @param string      $eventName a TheliaEvent name, as defined in TheliaEvents class
     * @param ActionEvent $event     the event
     */
    protected function dispatch($eventName, ActionEvent $event = null)
    {
        $this->getDispatcher()->dispatch($eventName, $event);
    }

    /**
     * Return the event dispatcher,
     *
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * Return the parser context,
     *
     * @return ParserContext
     */
    protected function getParserContext()
    {
        return $this->container->get('thelia.parser.context');
    }

    /**
     * Return the security context, by default in admin mode.
     *
     * @return \Thelia\Core\Security\SecurityContext
     */
    protected function getSecurityContext($context = false)
    {
        $securityContext = $this->container->get('thelia.securityContext');

        $securityContext->setContext($context === false ? SecurityContext::CONTEXT_BACK_OFFICE : $context);

        return $securityContext;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function getRequest()
    {
        return $this->container->get('request');
    }

    /**
     * Returns the session from the current request
     *
     * @return \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    protected function getSession()
    {
        $request = $this->getRequest();

        return $request->getSession();
    }

    /**
     *
     * redirect request to specify url
     * @param string $url
     */
    public function redirect($url)
    {
        Redirect::exec($url);
    }

    /**
     * If success_url param is present in request, follow this link.
     */
    protected function redirectSuccess()
    {
        if (null !== $url = $this->getRequest()->get("success_url")) {
            $this->redirect($url);
        }
    }
}
