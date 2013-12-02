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
namespace Thelia\TaxEngine;

use Symfony\Component\HttpFoundation\Session\Session;
use Thelia\Model\AddressQuery;
use Thelia\Model\CountryQuery;

/**
 * Class TaxEngine
 * @package Thelia\TaxEngine
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxEngine
{
    protected static $instance = null;

    protected static $taxCountry = null;

    /**
     * @var Session $session
     */
    protected $session = null;

    static public function getInstance(Session $session = null)
    {
        if(null === self::$instance) {
            self::$instance = new TaxEngine();
        }

        if(null !== self::$instance) {
            self::$instance->setSession($session);
        }

        return self::$instance;
    }

    protected function setSession(Session $session)
    {
        $this->session = $session;
    }

    private function getTaxTypeDirectory()
    {
        return __DIR__ . "/TaxType";
    }

    public function getTaxTypeList()
    {
        $typeList = array();

        try {
            $directoryBrowser = new \DirectoryIterator($this->getTaxTypeDirectory($this->getTaxTypeDirectory()));
        } catch (\UnexpectedValueException $e) {
            return $typeList;
        }

        /* browse the directory */
        foreach ($directoryBrowser as $directoryContent) {
            /* is it a file ? */
            if (!$directoryContent->isFile()) {
                continue;
            }

            $fileName = $directoryContent->getFilename();
            $className = substr($fileName, 0, (1+strlen($directoryContent->getExtension())) * -1);

            if($className == "BaseTaxType") {
                continue;
            }

            $typeList[] = $className;
        }

        return $typeList;
    }

    /**
     * Find Tax Country Id
     * First look for a picked delivery address country
     * Then look at the current customer default address country
     * Else look at the default website country
     *
     * @param bool $force result is static cached ; even if a below parameter change between 2 calls, we need to keep coherent results. but you can force it.
     * @return null|TaxEngine
     */
    public function getTaxCountry($force = false)
    {
        if(false === $force || null === self::$taxCountry) {
            /* is there a logged in customer ? */
            if(null !== $customer = $this->session->getCustomerUser()) {
                if (null !== $this->session->getOrder()
                        && null !== $this->session->getOrder()->chosenDeliveryAddress
                        && null !== $currentDeliveryAddress = AddressQuery::create()->findPk($this->session->getOrder()->chosenDeliveryAddress)) {
                    $taxCountry = $currentDeliveryAddress->getCountry();
                } else {
                    $customerDefaultAddress = $customer->getDefaultAddress();
                    $taxCountry = $customerDefaultAddress->getCountry();
                }
            } else {
                $taxCountry = CountryQuery::create()->findOneByByDefault(1);
            }

            self::$taxCountry = $taxCountry;
        }

        return self::$taxCountry;
    }
}
