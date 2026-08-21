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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\Country as BaseCountry;
use Thelia\Model\Map\CountryTableMap;

class Country extends BaseCountry
{
    protected static ?Country $defaultCountry = null;

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
    public function getZipCodeRE(): ?string
    {
        $zipCodeFormat = $this->getZipCodeFormat();

        if (empty($zipCodeFormat)) {
            return null;
        }

        $zipCodeRE = preg_replace('/\\s+/', ' ', $zipCodeFormat);

        $trans = [
            'N' => '\\d',
            'L' => '[a-zA-Z]',
            'C' => '.+',
            ' ' => ' +',
        ];

        return '#^'.strtr($zipCodeRE, $trans).'$#';
    }

    /**
     * Whether a customer has a state to pick for this country.
     *
     * `has_states` says a state is mandatory, not that the country carries any: a
     * country may hold states without requiring one (France and its departments), and
     * a country flagged as requiring one may hold none yet. What an address form can
     * offer is therefore the visible state rows attached to the country, this method,
     * while `has_states` only decides whether leaving the field empty is an error.
     */
    public function hasSelectableStates(): bool
    {
        if (null === $this->getId()) {
            return false;
        }

        return StateQuery::create()
            ->filterByCountryId($this->getId())
            ->filterByVisible(1)
            ->count() > 0;
    }

    /**
     * This method ensure backward compatibility to Thelia 2.1, where a country belongs to one and
     * only one shipping zone.
     *
     * @deprecated a country may belong to several Areas (shipping zones). Use CountryArea queries instead
     */
    public function getAreaId(): ?int
    {
        $firstAreaCountry = CountryAreaQuery::create()->findOneByCountryId($this->getId());

        if (null !== $firstAreaCountry) {
            return $firstAreaCountry->getAreaId();
        }

        return null;
    }

    /**
     * Put the current country as the default one.
     *
     * @throws \RuntimeException
     * @throws \Exception
     * @throws PropelException
     */
    public function toggleDefault(): void
    {
        if (null === $this->getId()) {
            throw new \RuntimeException('impossible to just uncheck default country, choose a new one');
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
        } catch (\Throwable $throwable) {
            $con->rollBack();

            throw $throwable;
        }
    }

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        return !$this->getByDefault();
    }

    /**
     * Return the default country.
     *
     * @throws \LogicException if no default country is defined
     */
    public static function getDefaultCountry()
    {
        if (null === self::$defaultCountry) {
            self::$defaultCountry = CountryQuery::create()->findOneByByDefault(true);

            if (null === self::$defaultCountry) {
                throw new \LogicException(Translator::getInstance()->trans('Cannot find a default country. Please define one.'));
            }
        }

        return self::$defaultCountry;
    }

    /**
     * @internal
     */
    public static function resetDefaultCountryCache(): void
    {
        self::$defaultCountry = null;
    }

    /**
     * Return the shop country.
     *
     * @throws \LogicException if no shop country is defined
     */
    public static function getShopLocation()
    {
        $countryId = ConfigQuery::getStoreCountry();

        // return the default country if no shop country defined
        if (empty($countryId)) {
            return self::getDefaultCountry();
        }

        $shopCountry = CountryQuery::create()->findPk($countryId);

        if (null === $shopCountry) {
            throw new \LogicException(Translator::getInstance()->trans('Cannot find the shop country. Please select a shop country.'));
        }

        return $shopCountry;
    }
}
