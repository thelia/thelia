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
use Thelia\Core\HttpFoundation\Request;

/**
 * Class TaxEngine
 *
 * @package Thelia\TaxEngine
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxEngine
{
    protected $taxCountry = null;
    protected $typeList = null;

    protected $taxTypesDirectories = array();

    /**
     * @var Session $session
     */
    protected $session = null;

    public function __construct(Request $request)
    {
        $this->session = $request->getSession();

        // Intialize the defaults Tax Types
        $this->taxTypesDirectories['Thelia\\TaxEngine\\TaxType'] = __DIR__ . DS . "TaxType";
    }

    /**
     * Add a directroy which contains tax types classes. The tax engine
     * will scan this directory, and add all the tax type classes.
     *
     * @param string $namespace                the namespace of the classes in the directory
     * @param string $path_to_tax_type_classes the path to the directory
     */
    public function addTaxTypeDirectory($namespace, $path_to_tax_type_classes)
    {
        $this->taxTypesDirectories[$namespace] = $path_to_tax_type_classes;
    }

    /**
     * Add a tax type to the current list.
     *
     * @param unknown $fullyQualifiedclassName the fully qualified classname, su chas MyTaxes\Taxes\MyTaxType
     *
     */
    public function addTaxType($fullyQualifiedclassName)
    {
        $this->typeList[] = $fullyQualifiedclassName;
    }

    public function getTaxTypeList()
    {
        if ($this->typeList === null) {

            $this->typeList = array();

            foreach ($this->taxTypesDirectories as $namespace => $directory) {

                try {
                    $directoryIterator = new \DirectoryIterator($directory);

                    foreach ($directoryIterator as $fileinfo) {

                        if ($fileinfo->isFile()) {

                            $fileName  = $fileinfo->getFilename();
                            $className = substr($fileName, 0, (1+strlen($fileinfo->getExtension())) * -1);

                            try {
                                $fullyQualifiedClassName = "$namespace\\$className";

                                $instance = new $fullyQualifiedClassName;

                                if ($instance instanceof BaseTaxType) {
                                    $this->addTaxType(get_class($instance));
                                }
                            } catch (\Exception $ex) {
                                // Nothing special to do
                            }
                        }
                    }
                } catch (\UnexpectedValueException $e) {
                    // Nothing special to do
                }
            }
        }

        return $this->typeList;
    }

    /**
     * Find Tax Country Id
     * First look for a picked delivery address country
     * Then look at the current customer default address country
     * Else look at the default website country

     * @return null|TaxEngine
     */
    public function getDeliveryCountry()
    {
        if (null === $this->taxCountry) {

            /* is there a logged in customer ? */
            if (null !== $customer = $this->session->getCustomerUser()) {
                if (null !== $this->session->getOrder()
                        && null !== $this->session->getOrder()->chosenDeliveryAddress
                        && null !== $currentDeliveryAddress = AddressQuery::create()->findPk($this->session->getOrder()->chosenDeliveryAddress)) {
                    $this->taxCountry = $currentDeliveryAddress->getCountry();
                } else {
                    $customerDefaultAddress = $customer->getDefaultAddress();
                    $this->taxCountry = $customerDefaultAddress->getCountry();
                }
            }

            if (null == $this->taxCountry) {
                $this->taxCountry = CountryQuery::create()->findOneByByDefault(1);
            }
        }

        return $this->taxCountry;
    }
}
