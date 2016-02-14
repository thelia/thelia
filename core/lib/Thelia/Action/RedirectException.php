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
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\HttpKernel\Exception\RedirectException as ExceptionRedirectException;

/**
 * Class RedirectException
 * @package Thelia\Action
 * @author manuel raynaud <manu@raynaud.io>
 */
class RedirectException extends BaseAction implements EventSubscriberInterface
{
    /** @var URL */
    protected $urlManager;

    public function __construct(URL $urlManager)
    {
        $this->urlManager = $urlManager;
    }

    public function checkRedirectException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if ($exception instanceof ExceptionRedirectException) {
            $response = RedirectResponse::create($exception->getUrl(), $exception->getStatusCode());
            $event->setResponse($response);
        } elseif ($exception instanceof AuthenticationException) {
            // Redirect to the login template
            $response = RedirectResponse::create($this->urlManager->viewUrl($exception->getLoginTemplate()));
            $event->setResponse($response);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => array("checkRedirectException", 128),
        ];
    }
}
