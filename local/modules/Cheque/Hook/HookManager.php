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

namespace Cheque\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class HookManager
 *
 * @package Cheque\Hook
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class HookManager extends BaseHook {

    public function onAdditionalPaymentInfo(HookRenderEvent $event)
    {
        $content = $this->render("order-placed.additional-payment-info.html", [
            'placed_order_id' => $event->getArgument('placed_order_id')
        ]);

        $event->add($content);
    }
} 