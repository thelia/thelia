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

namespace Thelia\Service\Model;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;

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

        foreach ($countries as $country) {
            $country->setLocale($locale);
            $choices[$country->getTitle()] = $country->getId();
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
