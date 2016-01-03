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

namespace VirtualProductControl\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductQuery;

/**
 * Class VirtualProductHook
 * @package VirtualProductHook\Hook
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

    public function onMainBeforeContent(HookRenderEvent $event)
    {
        if ($this->securityContext->isGranted(
            ["ADMIN"],
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
