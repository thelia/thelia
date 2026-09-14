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
 * E-mails the shop managers with the chosen message template.
 */
final readonly class SendShopManagersEmailAction extends AbstractEmailAction
{
    public static function getType(): string
    {
        return 'send_shop_managers_email';
    }

    public function execute(OrderStatusActionContext $context): void
    {
        // OrFail: a message that does not leave is the failure the merchant has to
        // see in the back office, so it must reach the runner instead of a log line.
        $this->mailer->sendEmailToShopManagersOrFail(
            $context->payload[self::FIELD_MESSAGE_CODE],
            $this->messageParameters($context),
        );
    }
}
