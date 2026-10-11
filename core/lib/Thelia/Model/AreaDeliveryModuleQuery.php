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

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Shipping\ZipCode\ZipCodePattern;
use Thelia\Model\Base\AreaDeliveryModuleQuery as BaseAreaDeliveryModuleQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'area_delivery_module' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class AreaDeliveryModuleQuery extends BaseAreaDeliveryModuleQuery
{
    /**
     * Check if a delivery module is suitable for the given country.
     *
     * With a postal code, a zone whose postal code rules refuse it is skipped: the module
     * is suitable when another of its zones covering the country accepts the code. Without
     * one, the rules are not read, as before they existed.
     *
     * @throws PropelException
     */
    public function findByCountryAndModule(Country $country, Module $module, ?State $state = null, ?string $zipCode = null): ?AreaDeliveryModule
    {
        $response = null;

        // A blank code is an unknown one: every zone takes it, no rule is read.
        if (null !== $zipCode && '' === ZipCodePattern::normalize($zipCode)) {
            $zipCode = null;
        }

        $countryInAreaList = CountryAreaQuery::findByCountryAndState($country, $state);

        /** @var CountryArea $countryInArea */
        foreach ($countryInAreaList as $countryInArea) {
            $response = self::create()->filterByAreaId($countryInArea->getAreaId())
                ->filterByModule($module)
                ->findOne();

            if (null !== $response && null !== $zipCode
                && !AreaZipCodeRuleQuery::ruleSetFor((int) $countryInArea->getAreaId(), (int) $country->getId())->accepts($zipCode)
            ) {
                $response = null;

                continue;
            }

            if (null !== $response) {
                break;
            }
        }

        return $response;
    }
}

// AreaDeliveryModuleQuery
