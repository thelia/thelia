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
                ->filterByStateId($state->getId())
                ->find();

            if (\count($countryAreaList) > 0) {
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
