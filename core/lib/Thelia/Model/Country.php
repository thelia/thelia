<?php

namespace Thelia\Model;

use Thelia\Model\Base\Country as BaseCountry;
use Thelia\Core\Translation\Translator;

class Country extends BaseCountry {

    /**
     * Return the default country
     *
     * @throws LogicException if no default country is defined
     */
    public static function getDefaultCountry() {
        $dc = CountryQuery::create()->findOneByByDefault(true);

        if ($dc == null)
            throw new \LogicException(Translator::getInstance()->trans("Cannot find a default country. Please define one."));

        return $dc;
    }

    /**
     * Return the shop country
     *
     * @throws LogicException if no shop country is defined
     */
    public static function getShopLocation() {
        $dc = CountryQuery::create()->findOneByShopCountry(true);

        if ($dc == null)
            throw new \LogicException(Translator::getInstance()->trans("Cannot find the shop country. Please select a shop country."));

        return $dc;
    }
}
