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
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductQuery;

/**
 * Class VirtualProductHook
 * @package VirtualProductHook\Hook
 * @author Manuel Raynaud <manu@thelia.net>
 */
class VirtualProductHook extends BaseHook
{

    public function onMainBeforeContent(HookRenderEvent $event)
    {
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
