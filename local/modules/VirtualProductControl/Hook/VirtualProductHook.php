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

namespace VirtualProductControl\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductQuery;

/**
 * Class VirtualProductHook.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class VirtualProductHook extends BaseHook
{
    /**
     * @var SecurityContext
     */
    protected $securityContext;

    public function __construct(SecurityContext $securityContext)
    {
        $this->securityContext = $securityContext;
    }

    public function onMainBeforeContent(HookRenderEvent $event): void
    {
        if ($this->securityContext->isGranted(
            ['ADMIN'],
            [AdminResources::PRODUCT],
            [],
            [AccessManager::VIEW]
        )) {
            $products = ProductQuery::create()
                ->filterByVirtual(1)
                ->filterByVisible(1)
                ->count();

            if ($products > 0) {
                $deliveryModule = ModuleQuery::create()->retrieveVirtualProductDelivery();

                if (false === $deliveryModule) {
                    $event->add($this->render('virtual-delivery-warning.html'));
                }
            }
        }
    }
}
