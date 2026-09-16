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

namespace Thelia\Domain\Localization\Service;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Lang;

readonly class CountryService
{
    public function __construct(protected Session $session)
    {
    }

    public function getAllCountriesChoiceType(): array
    {
        $choices = [];
        $countries = CountryQuery::create()
            ->filterByVisible(1)
            ->find();
        $locale = $this->session->getLang()->getLocale();
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        foreach ($countries as $country) {
            $country->setLocale($locale);
            $title = $country->getTitle();

            if (null === $title || '' === $title) {
                $country->setLocale($defaultLocale);
                $title = $country->getTitle();
            }

            // A country translated in no locale at all still has to be told
            // apart from the next one: keying it on its missing title put
            // every one of them under the same empty label, so the form
            // offered a single blank entry for all of them.
            $choices[$title ?: ($country->getIsoalpha2() ?: $country->getIsocode())] = $country->getId();
        }

        return $choices;
    }

    public function getDefaultCountry(): Country
    {
        $country = CountryQuery::create()
            ->filterByByDefault(1)
            ->limit(1)
            ->findOne();

        if (null === $country) {
            throw new \RuntimeException('No default country found');
        }

        $locale = $this->session->getLang()->getLocale();
        $country->setLocale($locale);

        return $country;
    }
}
