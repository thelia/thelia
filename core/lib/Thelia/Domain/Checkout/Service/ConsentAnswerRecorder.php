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

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\Exception\UnknownConsentException;
use Thelia\Model\Consent;

/**
 * Turns "yes to this code, no to that one" into answers the checkout can act on.
 *
 * A caller with no screens knows codes and nothing else, and an answer made of a code
 * and a boolean is not a proof: what turns it into one is the wording the shop is
 * publishing under that code, resolved in the language the order is about to be frozen
 * in. This is where the two are put together, and it is the same wording the payment
 * screen of a theme shows — read from the same provider, in the same locale.
 *
 * Every consent the shop is asking for comes out with an answer, not only the ones the
 * caller named: the ones it left out are refusals, and an order with no row for them
 * would later read as an order placed before they existed.
 */
final readonly class ConsentAnswerRecorder
{
    public function __construct(
        private ConsentProvider $consentProvider,
        private SubmittedConsentAnswers $submittedAnswers,
    ) {
    }

    /**
     * @param array<string, bool> $acceptedByCode what the caller answered, by consent code
     *
     * @throws UnknownConsentException when an answer names a consent the shop is not asking for
     * @throws PropelException
     */
    public function record(array $acceptedByCode, string $locale): void
    {
        if ([] === $acceptedByCode) {
            // Nothing was answered, so nothing is stated: whatever holds the answers of
            // this request — a session, for a caller that has one — keeps answering.
            return;
        }

        $activeConsents = $this->consentProvider->activeConsents();

        $this->refuseAnswersTheShopIsNotAskingFor($acceptedByCode, $activeConsents);

        $answers = [];

        foreach ($activeConsents as $consent) {
            $code = (string) $consent->getCode();

            $answers[$code] = [
                'accepted' => $acceptedByCode[$code] ?? false,
                'title' => $this->consentProvider->title($consent, $locale),
                'description' => $this->consentProvider->description($consent, $locale),
            ];
        }

        $this->submittedAnswers->submit($answers);
    }

    /**
     * @param array<string, bool> $acceptedByCode
     * @param list<Consent>       $activeConsents
     *
     * @throws UnknownConsentException
     */
    private function refuseAnswersTheShopIsNotAskingFor(array $acceptedByCode, array $activeConsents): void
    {
        $askedFor = array_map(static fn (Consent $consent): string => (string) $consent->getCode(), $activeConsents);

        foreach (array_keys($acceptedByCode) as $code) {
            if (!\in_array((string) $code, $askedFor, true)) {
                throw new UnknownConsentException((string) $code);
            }
        }
    }
}
