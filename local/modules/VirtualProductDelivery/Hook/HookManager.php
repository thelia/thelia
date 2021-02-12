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

namespace VirtualProductDelivery\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class HookManager.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookManager extends BaseHook
{
    public function onAccountOrderAfterProducts(HookRenderEvent $event)
    {
        $orderId = $event->getArgument('order');

        if (null !== $orderId) {
            $render = $this->render(
                'account-order-after-products.html',
                [
                    'order_id' => $orderId,
                ]
            );
            $event->add($render);
        }
    }
}
