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

use Colissimo\Model\Config\ColissimoConfigValue;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Translation\Translator;
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

    const DOMAIN_NAME = 'colissimo';

    public static function getPrices()
    {
        if (null === self::$prices) {
            self::$prices = json_decode(Colissimo::getConfigValue(ColissimoConfigValue::PRICES, null), true);
        }

        return self::$prices;
    }

    public function postActivation(ConnectionInterface $con = null)
    {
        self::setConfigValue(ColissimoConfigValue::ENABLED, 1);

        $database = new Database($con);
        $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));
    }

    public function isValidDelivery(Country $country)
    {
        if (0 == self::getConfigValue(ColissimoConfigValue::ENABLED, 1)) {
            return false;
        }

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
        $freeshipping = Colissimo::getConfigValue(ColissimoConfigValue::FREE_SHIPPING);
        $postage = 0;
        if (!$freeshipping) {
            $prices = self::getPrices();

            /* check if Colissimo delivers the asked area */
            if (!isset($prices[$areaId]) || !isset($prices[$areaId]["slices"])) {
                throw new DeliveryException(
                    Translator::getInstance()->trans(
                        "Colissimo delivery unavailable for the delivery country",
                        [],
                        self::DOMAIN_NAME
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
                        self::DOMAIN_NAME
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

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        $uploadDir = __DIR__ . '/Config/prices.json';

        $database = new Database($con);

        $tableExists = $database->execute("SHOW TABLES LIKE 'colissimo_freeshipping'")->rowCount();

        if (Colissimo::getConfigValue(ColissimoConfigValue::FREE_SHIPPING, null) == null && $tableExists) {
            $result = $database->execute('SELECT active FROM colissimo_freeshipping WHERE id=1')->fetch()["active"];
            Colissimo::setConfigValue(ColissimoConfigValue::FREE_SHIPPING, $result);
            $database->execute("DROP TABLE `colissimo_freeshipping`");
        }

        if (is_readable($uploadDir) && Colissimo::getConfigValue(ColissimoConfigValue::PRICES, null) == null) {
            Colissimo::setConfigValue(ColissimoConfigValue::PRICES, file_get_contents($uploadDir));
        }
    }
}
