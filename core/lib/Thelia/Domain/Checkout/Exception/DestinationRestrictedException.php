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

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Shipping\DestinationRestriction\RestrictedLine;

/**
 * Products of the cart cannot be shipped to the chosen address: a destination
 * restriction of their group refuses it. Only products of the buyer's own cart are
 * named, with the reason of their group, never the destinations it lists.
 *
 * A parcel is refused on the delivery address and a download on the invoice address:
 * each is told to change the address it was judged on, in a sentence of its own when
 * the cart holds both.
 */
class DestinationRestrictedException extends InvalidDeliveryException
{
    /**
     * @param list<RestrictedLine> $lines
     */
    public function __construct(
        public readonly array $lines,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $parcels = array_values(array_filter($lines, static fn (RestrictedLine $line): bool => !$line->judgedOnInvoiceAddress));
        $downloads = array_values(array_filter($lines, static fn (RestrictedLine $line): bool => $line->judgedOnInvoiceAddress));

        if ([] === $downloads) {
            CheckoutException::__construct(self::parcelSentence($parcels), $code, $previous, ['%products' => self::labels($parcels)]);

            return;
        }

        if ([] === $parcels) {
            CheckoutException::__construct(self::downloadSentence($downloads), $code, $previous, ['%products' => self::labels($downloads)]);

            return;
        }

        $translator = Translator::getInstance();

        CheckoutException::__construct('%parcels %downloads', $code, $previous, [
            '%parcels' => $translator->trans(self::parcelSentence($parcels), ['%products' => self::labels($parcels)]),
            '%downloads' => $translator->trans(self::downloadSentence($downloads), ['%products' => self::labels($downloads)]),
        ]);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliveryDestinationRestricted->value;
    }

    public function violationDetails(): array
    {
        return ['productIds' => array_map(static fn (RestrictedLine $line): int => $line->productId, $this->lines)];
    }

    /**
     * @param list<RestrictedLine> $lines
     */
    private static function parcelSentence(array $lines): string
    {
        return \count($lines) > 1
            ? '%products cannot be shipped to this address. Remove them from your cart or choose another address.'
            : '%products cannot be shipped to this address. Remove it from your cart or choose another address.';
    }

    /**
     * @param list<RestrictedLine> $lines
     */
    private static function downloadSentence(array $lines): string
    {
        return \count($lines) > 1
            ? '%products cannot be ordered with this invoice address. Remove them from your cart or choose another invoice address.'
            : '%products cannot be ordered with this invoice address. Remove it from your cart or choose another invoice address.';
    }

    /**
     * @param list<RestrictedLine> $lines
     */
    private static function labels(array $lines): string
    {
        return implode(', ', array_map(static fn (RestrictedLine $line): string => $line->label(), $lines));
    }
}
