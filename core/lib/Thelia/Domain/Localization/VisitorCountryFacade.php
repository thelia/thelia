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

namespace Thelia\Domain\Localization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryDetector;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryMode;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySession;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySettings;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;

/**
 * The country of the visitor, as detected by the shop and as the visitor settled it.
 *
 * Nothing here ever throws for a missing session: a console command or the stateless API
 * reads the default country of the shop, the way it did before the detection existed.
 */
final readonly class VisitorCountryFacade
{
    public function __construct(
        private VisitorCountrySettings $settings,
        private VisitorCountryDetector $detector,
        private VisitorCountrySession $session,
        private RequestStack $requestStack,
    ) {
    }

    public function mode(): VisitorCountryMode
    {
        return $this->settings->mode();
    }

    /**
     * The country found for this visitor, whether or not the shop uses it.
     */
    public function detectedCountry(): ?Country
    {
        if (VisitorCountryMode::Off === $this->mode()) {
            return null;
        }

        return $this->visibleCountry($this->session->detectedCountryId());
    }

    /**
     * The country the visitor shops in: the one applied or accepted, else the default
     * country of the shop.
     */
    public function currentCountry(): Country
    {
        if (VisitorCountryMode::Off !== $this->mode()) {
            $country = $this->visibleCountry($this->session->retainedCountryId());

            if (null !== $country) {
                return $country;
            }
        }

        return Country::getDefaultCountry();
    }

    /**
     * The country to offer to an anonymous visitor who has not answered yet. A customer
     * has addresses, which say where they shop.
     */
    public function suggestion(): ?Country
    {
        if (VisitorCountryMode::Suggest !== $this->mode() || $this->hasCustomer() || $this->session->isAnswered()) {
            return null;
        }

        $detected = $this->detectedCountry();

        if (null === $detected || $detected->getId() === $this->currentCountry()->getId()) {
            return null;
        }

        return $detected;
    }

    /**
     * @return bool false when the country is not one the shop shows, or there is no session to remember it
     */
    public function accept(int $countryId): bool
    {
        if (null === $this->visibleCountry($countryId) || !$this->session->isAvailable()) {
            return false;
        }

        $this->session->retain($countryId);
        $this->session->markAnswered();

        return true;
    }

    public function decline(): void
    {
        $this->session->markAnswered();
    }

    /**
     * For the callers that have no session, the API first: the detection of a request,
     * nothing remembered.
     */
    public function resolveFromRequest(Request $request): ?Country
    {
        if (VisitorCountryMode::Off === $this->mode()) {
            return null;
        }

        return $this->detector->detect($request);
    }

    private function visibleCountry(?int $countryId): ?Country
    {
        if (null === $countryId || $countryId < 1) {
            return null;
        }

        // A plain findPk() is answered from the instance pool: the tax engine asks once per
        // price, a filtered query would reach the database every time.
        $country = CountryQuery::create()->findPk($countryId);

        return 1 === $country?->getVisible() ? $country : null;
    }

    private function hasCustomer(): bool
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();

        return $session instanceof Session && null !== $session->getCustomerUser();
    }
}
