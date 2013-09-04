<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\Coupon\CouponCreateEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Coupon\CouponRuleCollection;
use Thelia\Form\CouponCreationForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Lang;
use Thelia\Tools\I18n;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Control View and Action (Model) via Events
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponController extends BaseAdminController
{
    /**
     * Manage Coupons list display
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function browseAction()
    {
        $this->checkAuth('ADMIN', 'admin.coupon.view');

        return $this->render('coupon-list');
    }

    /**
     * Manage Coupons creation display
     *
     * @param array $args GET arguments
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        // Check current user authorization
        $response = $this->checkAuth('admin.coupon.create');
        if ($response !==  null) {
            return $response;
        }

        $message = false;

        // Create the form from the request
        $creationForm = new CouponCreationForm($this->getRequest());

        if ($this->getRequest()->isMethod('POST')) {
            try {
                // Check the form against constraints violations
                $form = $this->validateForm($creationForm, 'POST');
                $i18n = new I18n();
                /** @var Lang $lang */
                $lang = $this->getSession()->get('lang');

                // Get the form field values
                $data = $form->getData();
                $couponEvent = new CouponCreateOrUpdateEvent(
                    $data['code'],
                    $data['title'],
                    $data['amount'],
                    $data['effect'],
                    $data['shortDescription'],
                    $data['description'],
                    $data['isEnabled'],
                    $i18n->getDateTimeFromForm($lang, $data['expirationDate']),
                    $data['isAvailableOnSpecialOffers'],
                    $data['isCumulative'],
                    $data['isRemovingPostage'],
                    $data['maxUsage'],
                    array(),
                    $data['locale']
                );

                $this->dispatch(
                    TheliaEvents::COUPON_CREATE,
                    $couponEvent
                );
                $this->adminLogAppend(
                    sprintf(
                        'Coupon %s (ID ) created',
                        $couponEvent->getTitle()
//                        $couponEvent->getCoupon()->getId()
                    )
                );
                // @todo redirect if successful
            } catch (FormValidationException $e) {
                // Invalid data entered
                $message = 'Please check your input:';
                $this->logError('creation', $message, $e);

            } catch (\Exception $e) {
                // Any other error
                $message = 'Sorry, an error occurred:';
                $this->logError('creation', $message, $e);
            }

            if ($message !== false) {
                // Mark the form as with error
                $creationForm->setErrorMessage($message);

                // Send the form and the error to the parser
                $this->getParserContext()
                    ->addForm($creationForm)
                    ->setGeneralError($message);
            }
        }

        $formAction = 'admin/coupon/create';

        return $this->render(
            'coupon-create',
            array(
                'formAction' => $formAction
            )
        );
    }

    /**
     * Manage Coupons edition display
     *
     * @param array $args GET arguments
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction($couponId)
    {
        $this->checkAuth('ADMIN', 'admin.coupon.edit');

        $formAction = 'admin/coupon/edit/' . $couponId;

        return $this->render('coupon-edit', array('formAction' => $formAction));
    }

    /**
     * Manage Coupons read display
     *
     * @param int $couponId Coupon Id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function readAction($couponId)
    {
        $this->checkAuth('ADMIN', 'admin.coupon.read');

        // Database request repeated in the loop but cached
        $search = CouponQuery::create();
        $coupon = $search->findOneById($couponId);

        if ($coupon === null) {
            return $this->pageNotFound();
        }

        return $this->render('coupon-read', array('couponId' => $couponId));
    }

    /**
     * Build a Coupon from its form
     *
     * @param array $data Form data
     *
     * @return Coupon
     */
    protected function buildCouponFromForm(array $data)
    {
        $couponBeingCreated = new Coupon();
        $couponBeingCreated->setCode($data['code']);
        $couponBeingCreated->setType($data['type']);
        $couponBeingCreated->setTitle($data['title']);
        $couponBeingCreated->setShortDescription($data['shortDescription']);
        $couponBeingCreated->setDescription($data['description']);
        $couponBeingCreated->setAmount($data['amount']);
        $couponBeingCreated->setIsEnabled($data['isEnabled']);
        $couponBeingCreated->setExpirationDate($data['expirationDate']);
        $couponBeingCreated->setSerializedRules(
            new CouponRuleCollection(
                array()
            )
        );
        $couponBeingCreated->setIsCumulative($data['isCumulative']);
        $couponBeingCreated->setIsRemovingPostage(
            $data['isRemovingPostage']
        );
        $couponBeingCreated->setMaxUsage($data['maxUsage']);
        $couponBeingCreated->setIsAvailableOnSpecialOffers(
            $data['isAvailableOnSpecialOffers']
        );

        return $couponBeingCreated;
    }

    /**
     * Log error message
     *
     * @param string     $action  Creation|Update|Delete
     * @param string     $message Message to log
     * @param \Exception $e       Exception to log
     *
     * @return $this
     */
    protected function logError($action, $message, $e)
    {
        Tlog::getInstance()->error(
            sprintf(
                'Error during Coupon ' . $action . ' process : %s. Exception was %s',
                $message,
                $e->getMessage()
            )
        );

        return $this;
    }

}
