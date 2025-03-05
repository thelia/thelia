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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\AdminAccessDenied;

/**
 * Class ControllerListener.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ControllerListener implements EventSubscriberInterface
{
    public function __construct(protected SecurityContext $securityContext)
    {
    }

    public function adminFirewall(ControllerEvent $event): void
    {
        $controller = $event->getController();
        // check if an admin is logged in
        if (\is_array($controller) && $controller[0] instanceof BaseAdminController) {
            if (false === $this->securityContext->hasAdminUser() && (int) $event->getRequest()->attributes->get('not-logged') !== 1) {
                // Store the requested URL in the session
                $event->getRequest()->getSession()->set('admin_requested_url', $event->getRequest()->getRequestUri());

                throw new AdminAccessDenied(
                    Translator::getInstance()->trans(
                        "You're not currently connected to the administration panel. Please log in to access this page"
                    )
                );
            }

            if ($this->securityContext->hasAdminUser()) {
                $session = $event->getRequest()->getSession();

                // If we have a requested URL, redirect the user to this URL
                if ($session->has('admin_requested_url') && null !== $url = $session->get('admin_requested_url')) {
                    $session->remove('admin_requested_url');
                    throw new RedirectException($url);
                }
            }
        }
    }

    /**
     * @api
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['adminFirewall', 128],
            ],
        ];
    }
}
