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

namespace Colissimo\Controller;

use Colissimo\Colissimo;
use Colissimo\Model\ColissimoQuery;
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
use Thelia\Model\OrderStatusQuery;

/**
 * Class Export
 * @package Colissimo\Controller
 * @author Manuel Raynaud <manu@raynaud.io>
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

            // Get new status
            $status_id = $exportForm->get('status_id')->getData();
            $status = OrderStatusQuery::create()
                ->filterByCode($status_id)
                ->findOne();

            // Get Colissimo orders
            $orders = ColissimoQuery::getOrders()->find();

            $export = "";
            $store_name = ConfigQuery::getStoreName();

            /** @var $order \Thelia\Model\Order */
            foreach ($orders as $order) {

                $value = $exportForm->get('order_'.$order->getId())->getData();

                if ($value) {

                    // Get order information
                    $customer = $order->getCustomer();
                    $locale = $order->getLang()->getLocale();
                    $address = $order->getOrderAddressRelatedByDeliveryOrderAddressId();
                    $country = CountryQuery::create()->findPk($address->getCountryId());
                    $country->setLocale($locale);
                    $customerTitle = CustomerTitleQuery::create()->findPk($address->getCustomerTitleId());
                    $customerTitle->setLocale($locale);
                    $weight = $exportForm->get('order_weight_'.$order->getId())->getData();

                    if ($weight == 0) {
                        /** @var \Thelia\Model\OrderProduct $product */
                        foreach ($order->getOrderProducts() as $product) {
                            $weight += (double)$product->getWeight();
                        }
                    }

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

                    // Cellphone
                    $cellphone = $customer->getDefaultAddress()->getCellphone();

                    if (empty($cellphone)) {
                        $cellphone = $customer->getDefaultAddress()->getCellphone();

                        if (empty($cellphone)) {
                            $cellphone = self::DEFAULT_CELLPHONE;
                        }
                    }


                    $export .=
                        "\"".$order->getRef()
                        ."\";\"".$address->getLastname()
                        ."\";\"".$address->getFirstname()
                        ."\";\"".$address->getAddress1()
                        ."\";\"".$address->getAddress2()
                        ."\";\"".$address->getAddress3()
                        ."\";\"".$address->getZipcode()
                        ."\";\"".$address->getCity()
                        ."\";\"".$country->getIsoalpha2()
                        ."\";\"".$phone
                        ."\";\"".$cellphone
                        ."\";\"".$weight
                        ."\";\"".$customer->getEmail()
                        ."\";\"\";\"".$store_name
                        ."\";\"DOM\";\r\n";

                    if ($status) {
                        $event = new OrderEvent($order);
                        $event->setStatus($status->getId());
                        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
                    }
                }
            }

            return Response::create(
                utf8_decode($export),
                200,
                array(
                    "Content-Encoding"=>"ISO-8889-1",
                    "Content-Type"=>"application/csv-tab-delimited-table",
                    "Content-disposition"=>"filename=export.csv"
                )
            );

        } catch (FormValidationException $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("colissimo expeditor export", [], Colissimo::DOMAIN_NAME),
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
