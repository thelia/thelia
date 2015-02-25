<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/
/*************************************************************************************/

namespace Colissimo;

use Colissimo\Model\ColissimoFreeshippingQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\OrderException;
use Thelia\Install\Database;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Country;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\Exception\DeliveryException;

class Colissimo extends AbstractDeliveryModule
{
    protected $request;
    protected $dispatcher;

    private static $prices = null;

    const JSON_PRICE_RESOURCE = "/Config/prices.json";

    const MESSAGE_DOMAIN = 'colissimo';

    public static function getPrices()
    {
        if (null === self::$prices) {
            self::$prices = json_decode(file_get_contents(sprintf('%s%s', __DIR__, self::JSON_PRICE_RESOURCE)), true);
        }

        return self::$prices;
    }

    public function isValidDelivery(Country $country)
    {
        if (null !== $area = $this->getAreaForCountry($country)) {
            $areaId = $area->getId();

            $prices = self::getPrices();

            /* Check if Colissimo delivers the area */
            if (isset($prices[$areaId]) && isset($prices[$areaId]["slices"])) {
                // Yes ! Check if the cart weight is below slice limit
                $areaPrices = $prices[$areaId]["slices"];
                ksort($areaPrices);

                /* Check cart weight is below the maximum weight */
                end($areaPrices);
                $maxWeight = key($areaPrices);

                $cartWeight = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getWeight();

                if ($cartWeight <= $maxWeight) {
                    return true;
                }
            }
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
                    Translator::getInstance()->trans(
                        "Colissimo delivery unavailable for the delivery country",
                        [],
                        self::MESSAGE_DOMAIN
                    )
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
                        array("%weight" => $weight),
                        self::MESSAGE_DOMAIN
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
        $cartWeight = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getWeight();

        $postage = self::getPostageAmount(
            $this->getAreaForCountry($country)->getId(),
            $cartWeight
        );

        return $postage;
    }
}
