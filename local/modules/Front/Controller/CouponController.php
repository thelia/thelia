<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Front\Controller;

use Propel\Runtime\Exception\PropelException;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\CouponCode;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Module\Exception\DeliveryException;

/**
 * Class CouponController
 * @package Thelia\Controller\Front
 * @author Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponController extends BaseFrontController
{

    /**
     * Test Coupon consuming
     */
    public function consumeAction()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        $message = false;
        $couponCodeForm = new CouponCode($this->getRequest());

        try {
            $form = $this->validateForm($couponCodeForm, 'post');

            $couponCode = $form->get('coupon-code')->getData();

            if (null === $couponCode || empty($couponCode)) {
                $message = true;
                throw new \Exception('Coupon code can\'t be empty');
            }

            $couponConsumeEvent = new CouponConsumeEvent($couponCode);

            // Dispatch Event to the Action
            $this->getDispatcher()->dispatch(TheliaEvents::COUPON_CONSUME, $couponConsumeEvent);

            /* recalculate postage amount */
            $order = $this->getSession()->getOrder();

            if (null !== $order) {
                $deliveryModule = $order->getModuleRelatedByDeliveryModuleId();
                $deliveryAddress = AddressQuery::create()->findPk($order->chosenDeliveryAddress);

                if (null !== $deliveryModule && null !== $deliveryAddress) {

                    $moduleInstance = $deliveryModule->getModuleInstance($this->container);

                    $orderEvent = new OrderEvent($order);

                    try {
                        $postage = $moduleInstance->getPostage($deliveryAddress->getCountry());

                        $orderEvent->setPostage($postage);

                        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_POSTAGE, $orderEvent);
                    }
                    catch (DeliveryException $ex) {
                        // The postage has been chosen, but changes dues to coupon causes an exception.
                        // Reset the postage data in the order
                        $orderEvent->setDeliveryModule(0);

                        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);
                    }
                }
            }

            $this->redirect($couponCodeForm->getSuccessUrl());

        } catch (FormValidationException $e) {
            $message = sprintf('Please check your coupon code: %s', $e->getMessage());
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = sprintf('Sorry, an error occurred: %s', $e->getMessage());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf("Error during order delivery process : %s. Exception was %s", $message, $e->getMessage()));

            $couponCodeForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($couponCodeForm)
                ->setGeneralError($message);
        }
    }
}
