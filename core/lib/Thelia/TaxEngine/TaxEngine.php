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

namespace Thelia\TaxEngine;

use Symfony\Component\HttpFoundation\Session\Session;
use Thelia\Model\AddressQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\State;

/**
 * Class TaxEngine
 *
 * @package Thelia\TaxEngine
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxEngine
{
    protected $taxCountry = null;
    protected $taxState = null;
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

     * @return null|Country
     */
    public function getDeliveryCountry()
    {
        if (null === $this->taxCountry) {
            /* is there a logged in customer ? */
            if (null !== $customer = $this->session->getCustomerUser()) {
                if (null !== $this->session->getOrder()
                        && null !== $this->session->getOrder()->getChoosenDeliveryAddress()
                        && null !== $currentDeliveryAddress = AddressQuery::create()->findPk($this->session->getOrder()->getChoosenDeliveryAddress())) {
                    $this->taxCountry = $currentDeliveryAddress->getCountry();
                    $this->taxState = $currentDeliveryAddress->getState();
                } else {
                    $customerDefaultAddress = $customer->getDefaultAddress();
                    $this->taxCountry = $customerDefaultAddress->getCountry();
                    $this->taxState = $customerDefaultAddress->getState();
                }
            }

            if (null == $this->taxCountry) {
                $this->taxCountry = CountryQuery::create()->findOneByByDefault(1);
                $this->taxState = null;
            }
        }

        return $this->taxCountry;
    }

    /**
     * Find Tax State Id
     *
     * First look for a picked delivery address state
     * Then look at the current customer default address state
     * Else null

     * @return null|State
     * @since 2.3.0-alpha1
     */
    public function getDeliveryState()
    {
        if (null === $this->taxCountry) {
            /* is there a logged in customer ? */
            $this->getDeliveryCountry();
        }

        return $this->taxState;
    }
}
