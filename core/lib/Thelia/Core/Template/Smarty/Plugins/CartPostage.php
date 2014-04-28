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

namespace Thelia\Core\Template\Smarty\Plugins;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Util\PropelModelPager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;

use Thelia\Core\Translation\Translator;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\Exception\DeliveryException;

/**
 * Class CartPostage
 * @package Thelia\Core\Template\Smarty\Plugins
 */
class CartPostage extends AbstractSmartyPlugin
{
    /** @var PropelModelPager[] */
    protected static $pagination = null;

    protected $loopDefinition = array();

    /** @var \Thelia\Core\HttpFoundation\Request The Request */
    protected $request;

    /** @var \Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher The Dispatcher */
    protected $dispatcher;

    /** @var \Thelia\Core\Security\SecurityContext The Security Context */
    protected $securityContext;

    /** @var Translator */
    protected $translator;

    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var integer $countryId the id of country */
    protected $countryId = null;

    /** @var integer $deliveryId the id of the cheapest delivery  */
    protected $deliveryId = null;

    /** @var float $postage the postage amount */
    protected $postage = null;

    /** @var boolean $isCustomizable indicate if customer can change the country */
    protected $isCustomizable = true;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->request = $container->get('request');
        //$this->dispatcher = $container->get('event_dispatcher');
        //$this->securityContext = $container->get('thelia.securityContext');
        $this->translator = $container->get("thelia.translator");
    }

    /**
     * Get postage amount for cart
     *
     * @param array                     $params   Block parameters
     * @param mixed                     $content  Block content
     * @param \Smarty_Internal_Template $template Template
     * @param bool                      $repeat   Control how many times
     *                                            the block is displayed
     *
     * @return mixed
     */
    public function postage($params, $content, $template, &$repeat)
    {

        if (! $repeat) {
            return $content;
        }

        $customer = $this->request->getSession()->getCustomerUser();
        $country = $this->getDeliveryCountry($customer);

        if (null !== $country) {
            $this->countryId = $country->getId();
            // try to get the cheapest delivery for this country
            $this->getCheapestDelivery($country);
        }

        $template->assign('country_id', $this->countryId);
        $template->assign('delivery_id', $this->deliveryId);
        $template->assign('postage', $this->postage ?: 0.0);
        $template->assign('is_customizable', $this->isCustomizable);
    }

    /**
     * Retrieve the delivery country for a customer
     *
     * The rules :
     *  - the country of the delivery address of the customer related to the
     *      cart if it exists
     *  - the country saved in cookie if customer have changed
     *      the default country
     *  - the default country for the shop if exists
     *  - the country related to the customer based on browser preferences
     *  - default country
     *
     *
     * @param  \Thelia\Model\Customer $customer
     * @return \Thelia\Model\Country
     */
    protected function getDeliveryCountry(Customer $customer = null)
    {

        // get country from customer addresses
        if (null !== $customer) {
            $address = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->filterByIsDefault(1)
                ->findOne()
            ;

            if (null !== $address) {
                $this->isCustomizable = false;

                return $address->getCountry();
            }
        }

        // get country from cookie
        $cookieName = ConfigQuery::read('front_cart_country_cookie_name', 'fcccn');
        if ($this->request->cookies->has($cookieName)) {
            $cookieVal = $this->request->cookies->getInt($cookieName, 0);
            if (0 !== $cookieVal) {
                $country = CountryQuery::create()->findPk($cookieVal);
                if (null !== $country) {
                    return $country;
                }
            }
        }

        // get default country for store.
        try {
            $country = Country::getDefaultCountry();

            return $country;
        } catch (\LogicException $e) {
            ;
        }

        // get browser lang
        $lang_code= $this->request->getSession()->getLang()->getCode();
        if ($lang_code) {
            $country = CountryQuery::create()
                ->findOneByIsoalpha2(strtoupper($lang_code));
            if (null !== $country) {
                return $country;
            }
        }

        return null;

    }

    /**
     * Retrieve the cheapest delivery for country
     *
     * @param  \Thelia\Model\Country   $country
     * @return DeliveryModuleInterface
     */
    protected function getCheapestDelivery(Country $country)
    {

        $deliveryModules = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->find();
        ;

        foreach ($deliveryModules as $deliveryModule) {

            /** @var DeliveryModuleInterface $moduleInstance */
            $moduleInstance = $this->container->get(sprintf('module.%s', $deliveryModule->getCode()));

            if (false === $moduleInstance instanceof DeliveryModuleInterface) {
                throw new \RuntimeException(sprintf("delivery module %s is not a Thelia\Module\DeliveryModuleInterface", $deliveryModule->getCode()));
            }

            try {
                // Check if module is valid, by calling isValidDelivery(),
                // or catching a DeliveryException.
                if ($moduleInstance->isValidDelivery($country)) {

                    $postage = $moduleInstance->getPostage($country);
                    if (null === $this->postage || $this->postage > $postage) {
                        $this->postage = $postage;
                        $this->deliveryId = $deliveryModule->getId();
                    }
                }
            } catch (DeliveryException $ex) {
                // Module is not available
            }

        }

    }

    /**
     * Defines the various smarty plugins handled by this class
     *
     * @return SmartyPluginDescriptor[] smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('block', 'postage', $this, 'postage')
        );
    }
}
