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

namespace Colissimo\Controller;

use Colissimo\Model\ColissimoQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Colissimo\Form\Export as FormExport;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;


/**
 * Class Export
 * @package Colissimo\Controller
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Export extends BaseAdminController
{
    const DEFAULT_PHONE = "0100000000";
    const DEFAULT_CELLPHONE = "0600000000";

    public function exportAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('Colissimo'), AccessManager::UPDATE)) {
            return $response;
        }

        $form  = new FormExport($this->getRequest());

        try {
            $exportForm = $this->validateForm($form);

            $status_id = $exportForm->get('status_id')->getData();

            $status = OrderStatusQuery::create()
                ->filterByCode($status_id)
                ->findOne();

            $orders = ColissimoQuery::getOrders()
                ->find();

            $export = "";
            $store_name = ConfigQuery::read("store_name");
            /** @var $order \Thelia\Model\Order */
            foreach ($orders as $order) {

                $value = $exportForm->get('order_'.$order->getId())->getData();

                if ($value) {

                    $customer = $order->getCustomer();
                    $locale = $order->getLang()->getLocale();
                    $address = $order->getOrderAddressRelatedByDeliveryOrderAddressId();
                    $country = CountryQuery::create()->findPk($address->getCountryId());
                    $country->setLocale($locale);
                    $customerTitle = CustomerTitleQuery::create()->findPk($address->getCustomerTitleId());
                    $customerTitle->setLocale($locale);

                    /**
                     * Get user's phone & cellphone
                     * First get invoice address phone,
                     * If empty, try to get default address' phone.
                     * If still empty, set default value
                     */
                    $phone = $address->getPhone();
                    if (empty($phone)) {
                        $phone = $customer->getDefaultAddress()->getPhone();

                        if (empty($phone)) {
                            $phone = self::DEFAULT_PHONE;
                        }
                    }

                    /**
                     * Cellp
                     */
                    $cellphone = $customer->getDefaultAddress()->getCellphone();

                    if (empty($cellphone)) {
                        $cellphone = $customer->getDefaultAddress()->getCellphone();

                        if (empty($cellphone)) {
                            $cellphone = self::DEFAULT_CELLPHONE;
                        }
                    }

                    /**
                     * Compute package weight
                     */
                    $weight = 0;
                    /** @var \Thelia\Model\OrderProduct $product */
                    foreach ($order->getOrderProducts() as $product) {
                        $weight+=(double) $product->getWeight();
                    }

                    $export .= "\"".$order->getRef()."\";\"".$address->getLastname()."\";\"".$address->getFirstname()."\";\"".$address->getAddress1()."\";\"".$address->getAddress2()."\";\"".$address->getAddress3()."\";\"".$address->getZipcode()."\";\"".$address->getCity()."\";\"".$country->getTitle()."\";\"".$phone."\";\"".$cellphone."\";\"".$weight."\";\"\";\"\";\"".$store_name."\";\"DOM\";\r\n";

                    if ($status) {
                        $event = new OrderEvent($order);
                        $event->setStatus($status->getId());
                        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
                    }


                }

            }

            return Response::create(
                $export,
                200,
                array(
                    "Content-Type"=>"application/csv-tab-delimited-table",
                    "Content-disposition"=>"filename=export.csv"
                )
            );

        } catch (FormValidationException $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("colissimo expeditor export"),
                $e->getMessage(),
                $form,
                $e
            );

            return $this->render(
                "module-configure",
                array(
                    "module_code" => "Colissimo",
                )
            );
        }
    }

} 