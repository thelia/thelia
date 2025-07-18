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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Controller\Admin\BaseAdminController;
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
        if (\is_array($controller)
            && $controller[0] instanceof BaseAdminController
            && (false === $this->securityContext->hasAdminUser()
                && '1' !== $event->getRequest()->attributes->get('not-logged'))
        ) {
            throw new AdminAccessDenied(Translator::getInstance()->trans("You're not currently connected to the administration panel. Please log in to access this page"));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['adminFirewall', 128],
            ],
        ];
    }
}
