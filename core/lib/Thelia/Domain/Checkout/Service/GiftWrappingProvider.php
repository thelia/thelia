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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Country;
use Thelia\Model\GiftWrapping;
use Thelia\Model\GiftWrappingI18nQuery;
use Thelia\Model\GiftWrappingQuery;
use Thelia\Model\State;
use Thelia\Model\TaxRule;
use Thelia\Tools\I18n;

/**
 * The gift wrappings a shop offers right now, in the order the merchant put them in.
 *
 * A wrapping turned off is left out of every answer here: it is no longer offered, no
 * longer selectable and no longer chargeable. The orders that already carry one are
 * untouched, since what they show is the line frozen on them.
 *
 * The list is memoized on the instance for the same reason the consents are: the payment
 * step reads it from the component that renders the choice and from the summary that
 * prices it, for what is always the same answer within one request. The cache is dropped
 * the moment a wrapping is created, edited, deleted, reordered or toggled.
 */
final class GiftWrappingProvider implements EventSubscriberInterface
{
    /** @var list<GiftWrapping>|null */
    private ?array $activeGiftWrappingsCache = null;

    public function __construct(private readonly TaxCalculatorFactoryInterface $taxCalculatorFactory)
    {
    }

    /**
     * @return list<GiftWrapping>
     *
     * @throws PropelException
     */
    public function activeGiftWrappings(): array
    {
        return $this->activeGiftWrappingsCache ??= iterator_to_array(
            GiftWrappingQuery::create()
                ->filterByActive(1)
                ->orderByPosition()
                ->find(),
            false,
        );
    }

    /**
     * Whether the shop offers the service at all.
     *
     * What the theme asks before rendering anything: with no active wrapping the checkout
     * is the one it was before this feature existed — no heading, no empty block.
     *
     * @throws PropelException
     */
    public function isOffered(): bool
    {
        return [] !== $this->activeGiftWrappings();
    }

    /**
     * The wrapping behind an id the buyer sent, or null.
     *
     * Read off the active list rather than by primary key: an id naming a wrapping the
     * shop turned off is an id the checkout must refuse, and refusing it here means every
     * caller refuses it the same way.
     *
     * @throws PropelException
     */
    public function findActive(?int $giftWrappingId): ?GiftWrapping
    {
        if (null === $giftWrappingId) {
            return null;
        }

        foreach ($this->activeGiftWrappings() as $giftWrapping) {
            if ((int) $giftWrapping->getId() === $giftWrappingId) {
                return $giftWrapping;
            }
        }

        return null;
    }

    /**
     * The wording to show the buyer, and the one to freeze on their order line.
     *
     * Resolved the way the product title frozen on an order line is: the asked locale
     * first, any wording the shop actually wrote next, the code of the wrapping last.
     * What it never returns is the "DEFAULT TITLE" placeholder I18n forges when it finds
     * neither — a basket line saying that means nothing, and an invoice line saying that
     * is worse.
     */
    public function title(GiftWrapping $giftWrapping, string $locale): string
    {
        $title = (string) I18n::forceI18nRetrieving($locale, 'GiftWrapping', $giftWrapping->getId(), [])->getTitle();

        if ('' !== $title) {
            return $title;
        }

        $written = GiftWrappingI18nQuery::create()
            ->filterById($giftWrapping->getId())
            ->filterByTitle(null, Criteria::ISNOTNULL)
            ->filterByTitle('', Criteria::NOT_EQUAL)
            ->orderByLocale()
            ->findOne();

        return (string) ($written?->getTitle() ?? $giftWrapping->getCode());
    }

    /**
     * The sentence shown under the wording, when the shop wrote one.
     *
     * No fallback, unlike the title: an untranslated description is left empty and its
     * paragraph is simply not rendered.
     */
    public function description(GiftWrapping $giftWrapping, string $locale): string
    {
        return (string) I18n::forceI18nRetrieving($locale, 'GiftWrapping', $giftWrapping->getId(), [])->getDescription();
    }

    /**
     * What the buyer is charged for the service, tax included, for the country the order
     * is being delivered to.
     *
     * The one place that price is computed, so the figure shown on the choice, the figure
     * added to the summary and the figure frozen on the order line are the same figure.
     * Nothing the browser sends is read here.
     *
     * @throws PropelException
     */
    public function taxedPrice(GiftWrapping $giftWrapping, ?Country $country = null, ?State $state = null): float
    {
        $untaxedPrice = (float) $giftWrapping->getPrice();
        $taxRule = $giftWrapping->getTaxRule();

        // No country to tax against yet — a cart with no delivery address — and the service
        // reads at its bare price until there is one.
        if (!$country instanceof Country || !$taxRule instanceof TaxRule) {
            return round($untaxedPrice, 2);
        }

        return round(
            $this->taxCalculatorFactory
                ->createTaxCalculator()
                ->loadTaxRuleWithoutProduct($taxRule, $country, $state)
                ->getTaxedPrice($untaxedPrice),
            2,
        );
    }

    public function forgetCache(): void
    {
        $this->activeGiftWrappingsCache = null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::GIFT_WRAPPING_CREATE => ['forgetCache', 0],
            TheliaEvents::GIFT_WRAPPING_UPDATE => ['forgetCache', 0],
            TheliaEvents::GIFT_WRAPPING_DELETE => ['forgetCache', 0],
            TheliaEvents::GIFT_WRAPPING_TOGGLE_ACTIVE => ['forgetCache', 0],
            TheliaEvents::GIFT_WRAPPING_UPDATE_POSITION => ['forgetCache', 0],
        ];
    }
}
