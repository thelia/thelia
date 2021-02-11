<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use LogicException;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Country\CountryEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\Country as BaseCountry;
use Thelia\Model\Map\CountryTableMap;

class Country extends BaseCountry
{
    protected static $defaultCountry = null;

    /**
     * get a regex pattern according to the zip code format field
     * to match a zip code for this country.
     *
     * zip code format :
     * - N : number
     * - L : letter
     * - C : iso of a state
     *
     * @return string|null will return a regex to match the zip code, otherwise null will be return
     *                     if zip code format is not defined
     */
    public function getZipCodeRE()
    {
        $zipCodeFormat = $this->getZipCodeFormat();

        if (empty($zipCodeFormat)) {
            return null;
        }

        $zipCodeRE = preg_replace("/\\s+/", ' ', $zipCodeFormat);

        $trans = [
            "N" => "\\d",
            "L" => "[a-zA-Z]",
            "C" => ".+",
            " " => " +"
        ];

        $zipCodeRE = "#^" . strtr($zipCodeRE, $trans) . "$#";

        return $zipCodeRE;
    }

    /**
     * This method ensure backward compatibility to Thelia 2.1, where a country belongs to one and
     * only one shipping zone.
     *
     * @deprecated a country may belong to several Areas (shipping zones). Use CountryArea queries instead
     */
    public function getAreaId()
    {
        $firstAreaCountry = CountryAreaQuery::create()->findOneByCountryId($this->getId());

        if (null !== $firstAreaCountry) {
            return $firstAreaCountry->getAreaId();
        }

        return null;
    }

    /**
     *
     * Put the current country as the default one.
     *
     * @throws \RuntimeException
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function toggleDefault()
    {
        if ($this->getId() === null) {
            throw new \RuntimeException("impossible to just uncheck default country, choose a new one");
        }

        $con = Propel::getWriteConnection(CountryTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            CountryQuery::create()
                ->filterByByDefault(1)
                ->update(['ByDefault' => 0], $con);

            $this
                ->setByDefault(1)
                ->save($con);

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        if ($this->getByDefault()) {
            return false;
        }

        return true;
    }

    /**
     * Return the default country
     *
     * @throws \LogicException if no default country is defined
     */
    public static function getDefaultCountry()
    {
        if (null === self::$defaultCountry) {
            self::$defaultCountry = CountryQuery::create()->findOneByByDefault(true);

            if (null === self::$defaultCountry) {
                throw new \LogicException(Translator::getInstance()->trans("Cannot find a default country. Please define one."));
            }
        }

        return self::$defaultCountry;
    }

    /**
     * Return the shop country
     *
     * @throws LogicException if no shop country is defined
     */
    public static function getShopLocation()
    {
        $countryId = ConfigQuery::getStoreCountry();

        // return the default country if no shop country defined
        if (empty($countryId)) {
            return self::getDefaultCountry();
        }

        $shopCountry = CountryQuery::create()->findPk($countryId);
        if ($shopCountry === null) {
            throw new \LogicException(Translator::getInstance()->trans("Cannot find the shop country. Please select a shop country."));
        }

        return $shopCountry;
    }
}
