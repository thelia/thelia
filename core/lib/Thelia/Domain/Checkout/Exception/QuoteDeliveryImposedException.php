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

namespace Thelia\Domain\Checkout\Exception;

use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;

/**
 * The cart of a quote ships the way the quote was agreed: the carrier it names, to the address it
 * names. Another carrier or another address would change the postage the buyer and the shop
 * agreed on, and the taxes that come with it.
 *
 * The details name what was refused: `field` is `deliveryModule` or `deliveryAddress`.
 */
final class QuoteDeliveryImposedException extends InvalidDeliveryException
{
    public const string FIELD_MODULE = 'deliveryModule';
    public const string FIELD_ADDRESS = 'deliveryAddress';

    private function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function aboutTheCarrier(): self
    {
        return new self('The carrier of a quote is the one it was agreed with and cannot be changed.', self::FIELD_MODULE);
    }

    public static function aboutTheAddress(): self
    {
        return new self('The delivery address of a quote is the one it was agreed with and cannot be changed.', self::FIELD_ADDRESS);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::QuoteDeliveryImposed->value;
    }

    public function violationDetails(): array
    {
        return ['field' => $this->field];
    }
}
