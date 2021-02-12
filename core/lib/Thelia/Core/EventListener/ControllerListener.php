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
    protected $securityContext;

    public function __construct(SecurityContext $securityContext)
    {
        $this->securityContext = $securityContext;
    }

    public function adminFirewall(ControllerEvent $event)
    {
        $controller = $event->getController();
        //check if an admin is logged in
        if ($controller[0] instanceof BaseAdminController) {
            if (false === $this->securityContext->hasAdminUser() && $event->getRequest()->attributes->get('not-logged') != 1) {
                throw new AdminAccessDenied(
                    Translator::getInstance()->trans(
                        "You're not currently connected to the administration panel. Please log in to access this page"
                    )
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => [
                ['adminFirewall', 128],
            ],
        ];
    }
}
