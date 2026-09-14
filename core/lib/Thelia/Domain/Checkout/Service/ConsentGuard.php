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
use Thelia\Domain\Checkout\Exception\MissingConsentException;
use Thelia\Domain\Localization\Service\LangService;

/**
 * Refuses an order the buyer has not given every required consent for.
 *
 * The check is made against the answers already recorded, not against the form that was
 * posted: a shop can add a mandatory consent between the moment the payment page was
 * rendered and the moment it is submitted, and the answer to a box that was never
 * displayed is not an acceptance.
 *
 * Where those answers are read from is ConsentAcceptanceReaderInterface's business, and
 * the session store is only its default: this guard is also on the path the progression
 * takes to say whether a cart may be paid for, and that verdict has to be reachable
 * from the API and from a command line, where there is no session to read.
 */
final readonly class ConsentGuard
{
    public function __construct(
        private ConsentProvider $consentProvider,
        private ConsentAcceptanceReaderInterface $acceptanceStore,
        private LangService $langService,
    ) {
    }

    /**
     * @throws MissingConsentException on the first required consent left unaccepted
     * @throws PropelException
     */
    public function checkMandatoryConsentsAccepted(): void
    {
        $acceptances = $this->acceptanceStore->all();

        foreach ($this->consentProvider->mandatoryConsents() as $consent) {
            $code = (string) $consent->getCode();

            if ($acceptances[$code] ?? false) {
                continue;
            }

            throw new MissingConsentException($code, $this->consentProvider->title($consent, (string) $this->langService->getLocale()));
        }
    }
}
