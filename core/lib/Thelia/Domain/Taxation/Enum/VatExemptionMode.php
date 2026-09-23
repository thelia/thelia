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

namespace Thelia\Domain\Taxation\Enum;

use Thelia\Model\ConfigQuery;

/**
 * When a shop lets an order leave without VAT because its buyer is liable for it.
 *
 * DISABLED is the default and is what Thelia has always done. The other value
 * moves the VAT a shop declares, so it is opt-in: an upgrade must never change
 * that figure on its own, and a shop that turns it on without a verification
 * module still exempts nobody, since no address can become verified.
 */
enum VatExemptionMode: string
{
    /** Every order is taxed, whatever its buyer declares. */
    case DISABLED = 'disabled';

    /** An order is exempt when its billing address carries a verified VAT number. */
    case VERIFIED_VAT_NUMBER = 'verified_vat_number';

    public const CONFIG_KEY = 'vat_exemption_mode';

    /**
     * The mode the shop configured, DISABLED when the variable is unset or holds
     * a value this version does not know.
     */
    public static function fromShopConfiguration(): self
    {
        return self::tryFrom((string) ConfigQuery::read(self::CONFIG_KEY, self::DISABLED->value))
            ?? self::DISABLED;
    }
}
