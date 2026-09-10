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

namespace Thelia\Domain\Order\StatusAction\Effect;

use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;

/**
 * E-mails the customer, in their language, with the chosen message template.
 */
final class SendCustomerEmailAction extends AbstractEmailAction
{
    public static function getType(): string
    {
        return 'send_customer_email';
    }

    public function execute(OrderStatusActionContext $context): void
    {
        $this->mailer->sendEmailToCustomer(
            $context->payload[self::FIELD_MESSAGE_CODE],
            $context->order->getCustomer(),
            $this->messageParameters($context),
        );
    }
}
