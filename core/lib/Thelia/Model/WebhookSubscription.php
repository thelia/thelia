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

namespace Thelia\Model;

use Thelia\Model\Base\WebhookSubscription as BaseWebhookSubscription;

/**
 * Skeleton subclass for representing a row from the 'webhook_subscription' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class WebhookSubscription extends BaseWebhookSubscription
{
    /**
     * @return list<string>
     */
    public function getEventNames(): array
    {
        $names = json_decode((string) $this->getEvents(), true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($names) ? array_values(array_filter($names, \is_string(...))) : [];
    }
}
