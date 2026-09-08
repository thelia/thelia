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
use Thelia\Model\Consent;
use Thelia\Model\ConsentI18nQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Tools\I18n;

/**
 * The consents a shop asks for right now, in the order the merchant put them in.
 *
 * A consent turned off is left out of every answer here: it is no longer displayed, no
 * longer required and no longer written on new orders. The acceptances already
 * collected are untouched, which is the whole point of turning one off rather than
 * deleting it.
 *
 * The list is memoized on the instance: the service is shared for the whole request, and
 * the payment step of the checkout reads it from several places (the Payment component,
 * the next-button guard, the validation guard) for what is always the same answer within
 * that request. The cache is dropped the moment a consent is created, edited, deleted,
 * reordered or toggled, so a change made through the back office during the same request
 * is seen immediately.
 */
final class ConsentProvider implements EventSubscriberInterface
{
    /** @var list<Consent>|null */
    private ?array $activeConsentsCache = null;

    /**
     * @return list<Consent>
     *
     * @throws PropelException
     */
    public function activeConsents(): array
    {
        return $this->activeConsentsCache ??= iterator_to_array(
            ConsentQuery::create()
                ->filterByActive(1)
                ->orderByPosition()
                ->find(),
            false,
        );
    }

    /**
     * @return list<Consent>
     *
     * @throws PropelException
     */
    public function mandatoryConsents(): array
    {
        return array_values(array_filter(
            $this->activeConsents(),
            static fn (Consent $consent): bool => $consent->isMandatory(),
        ));
    }

    /**
     * The wording to show the buyer, and the one to freeze on their order.
     *
     * Resolved the way the product title frozen on an order line is: the asked locale
     * first, the shop default next. What it never returns is the "DEFAULT TITLE"
     * placeholder I18n forges when it finds neither — hence the empty $needed. A box
     * saying that means nothing to the buyer, and a row saying that proves nothing to
     * the merchant, so any wording the shop actually wrote comes first, and the code of
     * the consent last: technical, but true.
     */
    public function title(Consent $consent, string $locale): string
    {
        $title = (string) I18n::forceI18nRetrieving($locale, 'Consent', $consent->getId(), [])->getTitle();

        if ('' !== $title) {
            return $title;
        }

        $written = ConsentI18nQuery::create()
            ->filterById($consent->getId())
            ->filterByTitle(null, Criteria::ISNOTNULL)
            ->filterByTitle('', Criteria::NOT_EQUAL)
            ->orderByLocale()
            ->findOne();

        return (string) ($written?->getTitle() ?? $consent->getCode());
    }

    /**
     * The long text shown under the box, and frozen on the order beside the wording.
     *
     * No fallback wording here, unlike the title: an untranslated long text is left
     * empty and its paragraph is simply not rendered.
     */
    public function description(Consent $consent, string $locale): string
    {
        return (string) I18n::forceI18nRetrieving($locale, 'Consent', $consent->getId(), [])->getDescription();
    }

    public function forgetCache(): void
    {
        $this->activeConsentsCache = null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CONSENT_CREATE => ['forgetCache', 0],
            TheliaEvents::CONSENT_UPDATE => ['forgetCache', 0],
            TheliaEvents::CONSENT_DELETE => ['forgetCache', 0],
            TheliaEvents::CONSENT_TOGGLE_ACTIVE => ['forgetCache', 0],
            TheliaEvents::CONSENT_UPDATE_POSITION => ['forgetCache', 0],
        ];
    }
}
