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
use Symfony\Component\Routing\Router;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\ConditionManagerInterface;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Coupon\CouponManager;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\Type\CouponInterface;
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
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function browseAction()
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $args['urlReadCoupon'] = $this->getRoute(
            'admin.coupon.read',
            array('couponId' => 0),
            Router::ABSOLUTE_URL
        );

        $args['urlEditCoupon'] = $this->getRoute(
            'admin.coupon.update',
            array('couponId' => 0),
            Router::ABSOLUTE_URL
        );

        $args['urlCreateCoupon'] = $this->getRoute(
            'admin.coupon.create',
            array(),
            Router::ABSOLUTE_URL
        );

        return $this->render('coupon-list', $args);
    }

    /**
     * Manage Coupons read display
     *
     * @param int $couponId Coupon Id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function readAction($couponId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        // Database request repeated in the loop but cached
        $search = CouponQuery::create();
        $coupon = $search->findOneById($couponId);

        if ($coupon === null) {
            return $this->pageNotFound();
        }

        $args['couponId'] = $couponId;
        $args['urlEditCoupon'] = $this->getRoute(
            'admin.coupon.update',
            array('couponId' => $couponId),
            Router::ABSOLUTE_URL
        );

        return $this->render('coupon-read', $args);
    }

    /**
     * Manage Coupons creation display
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function createAction()
    {
        // Check current user authorization
        $response = $this->checkAuth(AdminResources::COUPON, array(), AccessManager::CREATE);
        if ($response !==  null) {
            return $response;
        }

        // Parameters given to the template
        $args = array();

        $i18n = new I18n();
        /** @var Lang $lang */
        $lang = $this->getSession()->getLang();
        $eventToDispatch = TheliaEvents::COUPON_CREATE;

        if ($this->getRequest()->isMethod('POST')) {
            $this->validateCreateOrUpdateForm(
                $i18n,
                $lang,
                $eventToDispatch,
                'created',
                'creation'
            );
        } else {
            // If no input for expirationDate, now + 2 months
            $defaultDate = new \DateTime();
            $args['defaultDate'] = $defaultDate->modify('+2 month')
                ->format('Y-m-d');
        }

        $args['dateFormat'] = $this->getSession()->getLang()->getDateFormat();
        $args['availableCoupons'] = $this->getAvailableCoupons();
        $args['formAction'] = 'admin/coupon/create';

        return $this->render(
            'coupon-create',
            $args
        );
    }

    /**
     * Manage Coupons edition display
     *
     * @param int $couponId Coupon id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function updateAction($couponId)
    {
        // Check current user authorization
        $response = $this->checkAuth(AdminResources::COUPON, array(), AccessManager::UPDATE);
        if ($response !==  null) {
            return $response;
        }

        /** @var Coupon $coupon */
        $coupon = CouponQuery::create()->findPk($couponId);
        if (null === $coupon) {
            return $this->pageNotFound();

        }

        // Parameters given to the template
        $args = array();

        $i18n = new I18n();
        /** @var Lang $lang */
        $lang = $this->getSession()->getLang();
        $eventToDispatch = TheliaEvents::COUPON_UPDATE;

        // Update
        if ($this->getRequest()->isMethod('POST')) {
            $this->validateCreateOrUpdateForm(
                $i18n,
                $lang,
                $eventToDispatch,
                'updated',
                'update'
            );
        } else {
            // Display
            // Prepare the data that will hydrate the form
            /** @var ConditionFactory $conditionFactory */
            $conditionFactory = $this->container->get('thelia.condition.factory');
            $conditions = $conditionFactory->unserializeConditionCollection(
                $coupon->getSerializedConditions()
            );

            $data = array(
                'code' => $coupon->getCode(),
                'title' => $coupon->getTitle(),
                'amount' => $coupon->getAmount(),
                'type' => $coupon->getType(),
                'shortDescription' => $coupon->getShortDescription(),
                'description' => $coupon->getDescription(),
                'isEnabled' => $coupon->getIsEnabled(),
                'expirationDate' => $coupon->getExpirationDate('Y-m-d'),
                'isAvailableOnSpecialOffers' => $coupon->getIsAvailableOnSpecialOffers(),
                'isCumulative' => $coupon->getIsCumulative(),
                'isRemovingPostage' => $coupon->getIsRemovingPostage(),
                'maxUsage' => $coupon->getMaxUsage(),
                'conditions' => $conditions,
                'locale' => $coupon->getLocale(),
            );

            $args['conditionsObject'] = array();

            /** @var ConditionManagerInterface $condition */
            foreach ($conditions->getConditions() as $condition) {
                $args['conditionsObject'][] = array(
                    'serviceId' => $condition->getServiceId(),
                    'name' => $condition->getName(),
                    'tooltip' => $condition->getToolTip(),
                    'validators' => $condition->getValidators()
                );
            }

            $args['conditions'] = $this->cleanConditionForTemplate($conditions);

            // Setup the object form
            $changeForm = new CouponCreationForm($this->getRequest(), 'form', $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }
        $args['couponCode'] = $coupon->getCode();
        $args['availableCoupons'] = $this->getAvailableCoupons();
        $args['availableConditions'] = $this->getAvailableConditions();
        $args['urlAjaxGetConditionInput'] = $this->getRoute(
            'admin.coupon.condition.input',
            array('conditionId' => 'conditionId'),
            Router::ABSOLUTE_URL
        );

        $args['urlAjaxUpdateConditions'] = $this->getRoute(
            'admin.coupon.condition.update',
            array('couponId' => $couponId),
            Router::ABSOLUTE_URL
        );

        $args['formAction'] = 'admin/coupon/update/' . $couponId;

        return $this->render('coupon-update', $args);
    }

    /**
     * Manage Coupons read display
     *
     * @param string $conditionId Condition service id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function getConditionInputAction($conditionId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $this->checkXmlHttpRequest();

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');
        $inputs = $conditionFactory->getInputs($conditionId);

        if ($inputs === null) {
            return $this->pageNotFound();
        }

        return $this->render(
            'coupon/condition-input-ajax',
            array(
                'conditionId' => $conditionId,
                'inputs' => $inputs
            )
        );
    }

    /**
     * Manage Coupons read display
     *
     * @param int $couponId Coupon id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function updateConditionsAction($couponId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $this->checkXmlHttpRequest();

        $search = CouponQuery::create();
        /** @var Coupon $coupon */
        $coupon = $search->findOneById($couponId);

        if (!$coupon) {
            return $this->pageNotFound();
        }

        $conditions = new ConditionCollection();

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');
        $conditionsReceived = json_decode($this->getRequest()->get('conditions'));
        foreach ($conditionsReceived as $conditionReceived) {
            $condition = $conditionFactory->build(
                $conditionReceived->serviceId,
                (array) $conditionReceived->operators,
                (array) $conditionReceived->values
            );
            $conditions->add(clone $condition);
        }

        $couponEvent = new CouponCreateOrUpdateEvent(
            $coupon->getCode(),
            $coupon->getTitle(),
            $coupon->getAmount(),
            $coupon->getType(),
            $coupon->getShortDescription(),
            $coupon->getDescription(),
            $coupon->getIsEnabled(),
            $coupon->getExpirationDate(),
            $coupon->getIsAvailableOnSpecialOffers(),
            $coupon->getIsCumulative(),
            $coupon->getIsRemovingPostage(),
            $coupon->getMaxUsage(),
            $coupon->getLocale()
        );
        $couponEvent->setCouponModel($coupon);
        $couponEvent->setConditions($conditions);

        $eventToDispatch = TheliaEvents::COUPON_CONDITION_UPDATE;
        // Dispatch Event to the Action
        $this->dispatch(
            $eventToDispatch,
            $couponEvent
        );

        $this->adminLogAppend(
            AdminResources::COUPON, AccessManager::UPDATE,
            sprintf(
                'Coupon %s (ID %s) conditions updated',
                $couponEvent->getCouponModel()->getTitle(),
                $couponEvent->getCouponModel()->getType()
            )
        );

        $cleanedConditions = $this->cleanConditionForTemplate($conditions);

        return $this->render(
            'coupon/conditions',
            array(
                'couponId' => $couponId,
                'conditions' => $cleanedConditions,
                'urlEdit' => $couponId,
                'urlDelete' => $couponId
            )
        );
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
        $couponBeingCreated->setSerializedConditions(
            new ConditionCollection(
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

    /**
     * Validate the CreateOrUpdate form
     *
     * @param I18n   $i18n            Local code (fr_FR)
     * @param Lang   $lang            Local variables container
     * @param string $eventToDispatch Event which will activate actions
     * @param string $log             created|edited
     * @param string $action          creation|edition
     *
     * @return $this
     */
    protected function validateCreateOrUpdateForm(I18n $i18n, Lang $lang, $eventToDispatch, $log, $action)
    {
        // Create the form from the request
        $creationForm = new CouponCreationForm($this->getRequest());

        $message = false;
        try {
            // Check the form against conditions violations
            $form = $this->validateForm($creationForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            $couponEvent = new CouponCreateOrUpdateEvent(
                $data['code'], $data['title'], $data['amount'], $data['type'], $data['shortDescription'], $data['description'], $data['isEnabled'], \DateTime::createFromFormat('Y-m-d', $data['expirationDate']), $data['isAvailableOnSpecialOffers'], $data['isCumulative'], $data['isRemovingPostage'], $data['maxUsage'], $data['locale']
            );

            // Dispatch Event to the Action
            $this->dispatch(
                $eventToDispatch,
                $couponEvent
            );

            $this->adminLogAppend(
                AdminResources::COUPON, AccessManager::UPDATE,
                sprintf(
                    'Coupon %s (ID ) ' . $log,
                    $couponEvent->getTitle(),
                    $couponEvent->getCouponModel()->getId()
                )
            );

            $this->redirect(
                str_replace(
                    '{id}',
                    $couponEvent->getCouponModel()->getId(),
                    $creationForm->getSuccessUrl()
                )
            );

        } catch (FormValidationException $e) {
            // Invalid data entered
            $message = 'Please check your input:';

        } catch (\Exception $e) {
            // Any other error
            $message = 'Sorry, an error occurred:';
            $this->logError($action, $message, $e);
        }

        if ($message !== false) {
            // Mark the form as with error
            $creationForm->setErrorMessage($message);

            // Send the form and the error to the parser
            $this->getParserContext()
                ->addForm($creationForm)
                ->setGeneralError($message);
        }

        return $this;
    }

    /**
     * Get all available conditions
     *
     * @return array
     */
    protected function getAvailableConditions()
    {
        /** @var CouponManager $couponManager */
        $couponManager = $this->container->get('thelia.coupon.manager');
        $availableConditions = $couponManager->getAvailableConditions();
        $cleanedConditions = array();
        /** @var ConditionManagerInterface $availableCondition */
        foreach ($availableConditions as $availableCondition) {
            $condition = array();
            $condition['serviceId'] = $availableCondition->getServiceId();
            $condition['name'] = $availableCondition->getName();
           // $condition['toolTip'] = $availableCondition->getToolTip();
            $cleanedConditions[] = $condition;
        }

        return $cleanedConditions;
    }

    /**
     * Get all available coupons
     *
     * @return array
     */
    protected function getAvailableCoupons()
    {
        /** @var CouponManager $couponManager */
        $couponManager = $this->container->get('thelia.coupon.manager');
        $availableCoupons = $couponManager->getAvailableCoupons();
        $cleanedCoupons = array();
        /** @var CouponInterface $availableCoupon */
        foreach ($availableCoupons as $availableCoupon) {
            $condition = array();
            $condition['serviceId'] = $availableCoupon->getServiceId();
            $condition['name'] = $availableCoupon->getName();
            $condition['toolTip'] = $availableCoupon->getToolTip();
            $cleanedCoupons[] = $condition;
        }

        return $cleanedCoupons;
    }

    /**
     * Clean condition for template
     *
     * @param ConditionCollection $conditions Condition collection
     *
     * @return array
     */
    protected function cleanConditionForTemplate(ConditionCollection $conditions)
    {
        $cleanedConditions = array();
        /** @var $condition ConditionManagerInterface */
        foreach ($conditions->getConditions() as $condition) {
            $cleanedConditions[] = $condition->getToolTip();
        }

        return $cleanedConditions;
    }

}
