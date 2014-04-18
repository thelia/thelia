<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Colissimo;

use Colissimo\Model\ColissimoFreeshippingQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\OrderException;
use Thelia\Install\Database;
use Thelia\Model\Country;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\Exception\DeliveryException;

class Colissimo extends AbstractDeliveryModule
{
    protected $request;
    protected $dispatcher;

    private static $prices = null;

    const JSON_PRICE_RESOURCE = "/Config/prices.json";

    public static function getPrices()
    {
        if (null === self::$prices) {
            self::$prices = json_decode(file_get_contents(sprintf('%s%s', __DIR__, self::JSON_PRICE_RESOURCE)), true);
        }

        return self::$prices;
    }

    public function isValidDelivery(Country $country) {

        $areaId = $country->getAreaId();

        $prices = self::getPrices();

        /* Check if Colissimo delivers the area */
        if (isset($prices[$areaId]) && isset($prices[$areaId]["slices"])) {

            // Yes ! Check if the cart weight is below slice limit
            $areaPrices = $prices[$areaId]["slices"];
            ksort($areaPrices);

            /* Check cart weight is below the maximum weight */
            end($areaPrices);
            $maxWeight = key($areaPrices);

            $cartWeight = $this->getRequest()->getSession()->getCart()->getWeight();

            if ($cartWeight <= $maxWeight) return true;
        }

        return false;
    }

    /**
     * @param $areaId
     * @param $weight
     *
     * @return mixed
     * @throws \Thelia\Exception\OrderException
     */
    public static function getPostageAmount($areaId, $weight)
    {
        $freeshipping = ColissimoFreeshippingQuery::create()->getLast();
        $postage = 0;
        if (!$freeshipping) {
            $prices = self::getPrices();

            /* check if Colissimo delivers the asked area */
            if (!isset($prices[$areaId]) || !isset($prices[$areaId]["slices"])) {
                throw new DeliveryException(
                    Translator::getInstance()->trans("Colissimo delivery unavailable for the delivery country")
                );
            }

            $areaPrices = $prices[$areaId]["slices"];
            ksort($areaPrices);

            /* Check cart weight is below the maximum weight */
            end($areaPrices);
            $maxWeight = key($areaPrices);
            if ($weight > $maxWeight) {
                throw new DeliveryException(
                    Translator::getInstance()->trans(
                        "Colissimo delivery unavailable for this cart weight (%weight kg)",
                        array("%weight" => $weight)
                    )
                );
            }

            $postage = current($areaPrices);

            while (prev($areaPrices)) {
                if ($weight > key($areaPrices)) {
                    break;
                }

                $postage = current($areaPrices);
            }
        }
        return $postage;

    }

    public function postActivation(ConnectionInterface $con = null)
    {
        $database = new Database($con);

        $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));
    }

    /**
     *
     * calculate and return delivery price
     *
     * @param Country $country
     * @return mixed
     * @throws \Thelia\Exception\OrderException
     */
    public function getPostage(Country $country)
    {
        $cartWeight = $this->getRequest()->getSession()->getCart()->getWeight();

        $postage = self::getPostageAmount(
            $country->getAreaId(),
            $cartWeight
        );

        return $postage;
    }
}