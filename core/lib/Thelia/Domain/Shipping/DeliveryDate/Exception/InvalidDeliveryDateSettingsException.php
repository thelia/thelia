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

namespace Thelia\Domain\Shipping\DeliveryDate\Exception;

use Thelia\Core\Translation\Translator;

/**
 * A delivery date setting the merchant cannot save: a horizon shorter than the delay, a
 * slot ending before it starts, a shape the carrier does not accept. The message is written
 * for the merchant and already translated.
 */
final class InvalidDeliveryDateSettingsException extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(string $message, array $parameters = [])
    {
        parent::__construct(Translator::getInstance()->trans($message, $parameters));
    }
}
