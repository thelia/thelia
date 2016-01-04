<?php

namespace Thelia\Model;

use Thelia\Model\Base\CountryAreaQuery as BaseCountryAreaQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'country_area' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class CountryAreaQuery extends BaseCountryAreaQuery
{
    public static function findByCountryAndState(Country $country, State $state = null)
    {
        $response = null;

        if (null !== $state) {
            $countryAreaList = self::create()
                ->filterByCountryId($country->getId())
                ->filterByStateId($country->getId())
                ->find();

            if (count($countryAreaList) > 0) {
                return $countryAreaList;
            }
        }

        $countryAreaList = self::create()
            ->filterByCountryId($country->getId())
            ->filterByStateId(null)
            ->find()
        ;

        return $countryAreaList;
    }
}
