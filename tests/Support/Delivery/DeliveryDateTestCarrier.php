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

namespace Thelia\Tests\Support\Delivery;

use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\DeliveryDateAwareInterface;

/**
 * A carrier that takes delivery dates, delivers everywhere and charges a flat postage.
 *
 * The tests of delivery dates cannot lean on a shipped carrier: the version installed in a
 * checkout of the core is whatever was published, with or without the contract. The shapes
 * it accepts can be narrowed per test, to see a rule the carrier no longer honours ignored.
 */
final class DeliveryDateTestCarrier extends AbstractDeliveryModule implements DeliveryDateAwareInterface
{
    public const string POSTAGE = '5.00';

    /**
     * @var list<DeliveryDateChoiceMode>
     */
    public static array $acceptedModes = [DeliveryDateChoiceMode::Date, DeliveryDateChoiceMode::Slot];

    public function isValidDelivery(Country $country): bool
    {
        return true;
    }

    public function getPostage(Country $country): OrderPostage
    {
        return new OrderPostage((float) self::POSTAGE);
    }

    public function getAcceptedDeliveryDateChoiceModes(): array
    {
        return self::$acceptedModes;
    }
}
