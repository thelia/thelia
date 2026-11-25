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

namespace Thelia\Domain\Quote\Cart;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Quote\Enum\QuoteRevisionAddressKind;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Domain\Quote\Service\QuoteTotalsCalculator;
use Thelia\Model\CartAddress;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionAddress;
use Thelia\Model\QuoteRevisionAddressQuery;

/**
 * The postage of the cart of a quote is the one the shop and the buyer agreed on, whatever the
 * carrier would quote now: it is written from the accepted revision, the way a coupon that
 * removes the postage writes its own, but before it and before the carrier is asked.
 *
 * Nothing after it runs: the postage the revision carries is never quoted again, whatever
 * changes the amount of the cart or the choices of the checkout. Its taxes are the ones frozen on
 * the revision while the cart ships to the address of the quote; to another address, which only a
 * quote with no address of its own allows, they follow that address.
 */
final readonly class QuoteCartPostageSubscriber implements EventSubscriberInterface
{
    /**
     * Above the coupon that removes the postage (132) and the carrier (128).
     */
    public const int PRIORITY = 200;

    public function __construct(
        private QuoteCartInspector $inspector,
        private QuoteFacade $quotes,
        private QuoteTotalsCalculator $totalsCalculator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CART_SET_POSTAGE => ['forceThePostageOfTheQuote', self::PRIORITY],
        ];
    }

    public function forceThePostageOfTheQuote(CartCheckoutEvent $event): void
    {
        $cart = $event->getCart();

        if (!$this->inspector->isQuoteCart($cart)) {
            return;
        }

        $event->stopPropagation();

        $revision = $this->inspector->acceptedRevisionOf($cart);

        if (null === $revision) {
            // Nothing to write the postage from: it stays as the cart holds it.
            return;
        }

        $totals = $this->quotes->totals($revision);
        $postageTax = (float) $totals->postageTax;
        $quote = $this->inspector->quoteOf($cart);
        $address = $cart->getCartAddressRelatedByAddressDeliveryId();

        // Taxed as it was frozen, as long as the cart ships to the country the revision was frozen
        // for, whichever address of the book that is today. A quote that names no address leaves the
        // destination to the buyer, and the postage follows the country of the address kept.
        if ($quote instanceof Quote && $address instanceof CartAddress && null !== $address->getCountryId()
            && (int) $address->getCountryId() !== $this->frozenCountryIdOf($quote, $revision)) {
            $postageTax = (float) $this->totalsCalculator->postageTaxFor(
                $quote,
                $revision,
                QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find(),
                $address->getCountry(),
                null === $address->getStateId() ? null : $address->getState(),
            );
        }

        $cart
            ->setPostage(number_format((float) $totals->postageUntaxed + $postageTax, 6, '.', ''))
            ->setPostageTax(number_format($postageTax, 6, '.', ''))
            ->setPostageTaxRuleTitle(null)
            ->save();
    }

    /**
     * The country the postage of the revision was taxed for: the one of the copy of the delivery
     * address the revision was sent with, or, for a revision that carries no copy, the one of the
     * address the quote names today. 0 when there is none: nothing is known to keep, and the
     * postage is taxed where the cart ships.
     */
    private function frozenCountryIdOf(Quote $quote, QuoteRevision $revision): int
    {
        $copy = QuoteRevisionAddressQuery::create()
            ->filterByQuoteRevisionId($revision->getId())
            ->filterByKind(QuoteRevisionAddressKind::Delivery->value)
            ->findOne();

        if ($copy instanceof QuoteRevisionAddress) {
            return (int) $copy->getCountryId();
        }

        return null === $quote->getDeliveryAddressId() ? 0 : (int) $quote->getDeliveryAddress()?->getCountryId();
    }
}
