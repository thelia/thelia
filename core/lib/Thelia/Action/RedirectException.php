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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Tools\URL;

/**
 * Class RedirectException
 * @package Thelia\Action
 * @author manuel raynaud <manu@thelia.net>
 */
class RedirectException extends BaseAction implements EventSubscriberInterface
{

    /**
    * @var ParserInterface
    */
    protected $urlManager;

    public function __construct(URL $urlManager)
    {
        $this->urlManager = $urlManager;
    }

    public function checkRedirectException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if ($exception instanceof \Thelia\Core\HttpKernel\Exception\RedirectException) {
            $response = RedirectResponse::create($exception->getUrl(), $exception->getStatusCode());
            $event->setResponse($response);
        } elseif ($exception instanceof \Thelia\Core\Security\Exception\AuthenticationException) {
            // Redirect to the login template
            $response = RedirectResponse::create($this->urlManager->viewUrl($exception->getLoginTemplate()));
            $event->setResponse($response);
        }

    }
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => array("checkRedirectException", 128),
        ];
    }
}
