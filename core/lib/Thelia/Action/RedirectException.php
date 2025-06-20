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
namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpKernel\Exception\RedirectException as ExceptionRedirectException;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Tools\URL;

/**
 * Class RedirectException.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class RedirectException extends BaseAction implements EventSubscriberInterface
{
    public function checkRedirectException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof ExceptionRedirectException) {
            $response = new RedirectResponse($exception->getUrl(), $exception->getStatusCode());
            $event->setResponse($response);
        } elseif ($exception instanceof AuthenticationException) {
            // Redirect to the login template
            $response = new RedirectResponse(URL::getInstance()->viewUrl($exception->getLoginTemplate()));
            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['checkRedirectException', 128],
        ];
    }
}
