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

namespace VirtualProductDelivery\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Translation\Translator;

/**
 * Class HookManager
 * @package VirtualProductDelivery\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookManager extends BaseHook
{

    public function onDeliveryAddress(HookRenderEvent $event)
    {
        $event->add(
            $this->render("delivery-address.html")
        );
    }

    public function displayVirtualProducts(HookRenderEvent $event)
    {
        $orderId = $event->getArgument('order_id');

        if (null !== $orderId) {
            $event->add(
                $this->render('virtual-products-list.html', ['order_id' => $orderId])
            );
        }
    }
}
