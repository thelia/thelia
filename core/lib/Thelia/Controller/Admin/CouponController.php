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

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Coupon\CouponFactory;
use Thelia\Coupon\CouponManager;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\Type\CouponAbstract;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Coupon\Type\RemoveXPercent;
use Thelia\Form\CouponCreationForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Lang;
use Thelia\Tools\I18n;
use Thelia\Tools\Rest\ResponseRest;

/**
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
        $args['urlAjaxAdminCouponDrawInputs'] = $this->getRoute(
            'admin.coupon.draw.inputs',
            array('couponServiceId' => 'couponServiceId'),
            Router::ABSOLUTE_URL
        );
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
        /** @var CouponFactory $couponFactory */
        $couponFactory = $this->container->get('thelia.coupon.factory');
        $couponManager = $couponFactory->buildCouponFromModel($coupon);

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
                'update',
                $coupon
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

            $args['conditions'] = $this->cleanConditionForTemplate($conditions);

            // Setup the object form
            $changeForm = new CouponCreationForm($this->getRequest(), 'form', $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }
        $args['couponCode'] = $coupon->getCode();
        $args['availableCoupons'] = $this->getAvailableCoupons();
        $args['couponInputsHtml'] = $couponManager->drawBackOfficeInputs();
        $args['urlAjaxAdminCouponDrawInputs'] = $this->getRoute(
            'admin.coupon.draw.inputs.ajax',
            array('couponServiceId' => 'couponServiceId'),
            Router::ABSOLUTE_URL
        );
        $args['availableConditions'] = $this->getAvailableConditions();
        $args['urlAjaxGetConditionInputFromServiceId'] = $this->getRoute(
            'admin.coupon.draw.condition.read.inputs.ajax',
            array('conditionId' => 'conditionId'),
            Router::ABSOLUTE_URL
        );
        $args['urlAjaxGetConditionInputFromConditionInterface'] = $this->getRoute(
            'admin.coupon.draw.condition.update.inputs.ajax',
            array(
                'couponId' => $couponId,
                'conditionIndex' => 8888888
            ),
            Router::ABSOLUTE_URL
        );

        $args['urlAjaxSaveConditions'] = $this->getRoute(
            'admin.coupon.condition.save',
            array('couponId' => $couponId),
            Router::ABSOLUTE_URL
        );
        $args['urlAjaxDeleteConditions'] = $this->getRoute(
            'admin.coupon.condition.delete',
            array(
                'couponId' => $couponId,
                'conditionIndex' => 8888888
            ),
            Router::ABSOLUTE_URL
        );
        $args['urlAjaxGetConditionSummaries'] = $this->getRoute(
            'admin.coupon.draw.condition.summaries.ajax',
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
    public function getConditionEmptyInputAjaxAction($conditionId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $this->checkXmlHttpRequest();

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');
        $inputs = $conditionFactory->getInputsFromServiceId($conditionId);
        if (!$this->container->has($conditionId)) {
            return false;
        }

        /** @var ConditionInterface $condition */
        $condition = $this->container->get($conditionId);

        if ($inputs === null) {
            return $this->pageNotFound();
        }

        return $this->render(
            'coupon/condition-input-ajax',
            array(
                'inputsDrawn' => $condition->drawBackOfficeInputs(),
                'conditionServiceId' => $condition->getServiceId(),
                'conditionIndex' => -1,
            )
        );
    }

    /**
     * Manage Coupons read display
     *
     * @param int $couponId       Coupon id being updated
     * @param int $conditionIndex Coupon Condition position in the collection
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function getConditionToUpdateInputAjaxAction($couponId, $conditionIndex)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $this->checkXmlHttpRequest();

        $search = CouponQuery::create();
        /** @var Coupon $coupon */
        $coupon = $search->findOneById($couponId);
        if (!$coupon) {
            return $this->pageNotFound();
        }

        /** @var CouponFactory $couponFactory */
        $couponFactory = $this->container->get('thelia.coupon.factory');
        $couponManager = $couponFactory->buildCouponFromModel($coupon);

        if (!$couponManager instanceof CouponInterface) {
            return $this->pageNotFound();
        }

        $conditions = $couponManager->getConditions();
        if (!isset($conditions[$conditionIndex])) {
            return $this->pageNotFound();
        }
        /** @var ConditionInterface $condition */
        $condition = $conditions[$conditionIndex];

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');
        $inputs = $conditionFactory->getInputsFromConditionInterface($condition);

        if ($inputs === null) {
            return $this->pageNotFound();
        }

        return $this->render(
            'coupon/condition-input-ajax',
            array(
                'inputsDrawn' => $condition->drawBackOfficeInputs(),
                'conditionServiceId' => $condition->getServiceId(),
                'conditionIndex' => $conditionIndex,

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
    public function saveConditionsAction($couponId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $this->checkXmlHttpRequest();

        $search = CouponQuery::create();
        /** @var Coupon $coupon */
        $coupon = $search->findOneById($couponId);
        if (!$coupon) {
            return $this->pageNotFound();
        }

        $conditionToSave = $this->buildConditionFromRequest();

        /** @var CouponFactory $couponFactory */
        $couponFactory = $this->container->get('thelia.coupon.factory');
        $couponManager = $couponFactory->buildCouponFromModel($coupon);

        if (!$couponManager instanceof CouponInterface) {
            return $this->pageNotFound();
        }

        $conditions = $couponManager->getConditions();
        $conditionIndex = $this->getRequest()->request->get('conditionIndex');
        if ($conditionIndex >= 0) {
            // Update mode
            $conditions[$conditionIndex] = $conditionToSave;
        } else {
            // Insert mode
            $conditions[] = $conditionToSave;
        }
        $couponManager->setConditions($conditions);

        $this->manageConditionUpdate($coupon, $conditions);

        return new Response();
    }

    /**
     * Manage Coupons condition deleteion
     *
     * @param int $couponId       Coupon id
     * @param int $conditionIndex Coupon condition index in the collection
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function deleteConditionsAction($couponId, $conditionIndex)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);

        $this->checkXmlHttpRequest();

        $search = CouponQuery::create();
        /** @var Coupon $coupon */
        $coupon = $search->findOneById($couponId);
        if (!$coupon) {
            return $this->pageNotFound();
        }

        /** @var CouponFactory $couponFactory */
        $couponFactory = $this->container->get('thelia.coupon.factory');
        $couponManager = $couponFactory->buildCouponFromModel($coupon);

        if (!$couponManager instanceof CouponInterface) {
            return $this->pageNotFound();
        }

        $conditions = $couponManager->getConditions();
        unset($conditions[$conditionIndex]);
        $couponManager->setConditions($conditions);

        $this->manageConditionUpdate($coupon, $conditions);

        return new Response();
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
     * @param Coupon $model           Model if in update mode
     *
     * @return $this
     */
    protected function validateCreateOrUpdateForm(I18n $i18n, Lang $lang, $eventToDispatch, $log, $action, Coupon $model = null)
    {
        // Create the form from the request
        $creationForm = new CouponCreationForm($this->getRequest());

        $message = false;
        try {
            // Check the form against conditions violations
            $form = $this->validateForm($creationForm, 'POST');

            $couponEvent = $this->feedCouponCreateOrUpdateEvent($form, $model);

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
        /** @var ConditionInterface $availableCondition */
        foreach ($availableConditions as $availableCondition) {
            $condition = array();
            $condition['serviceId'] = $availableCondition->getServiceId();
            $condition['name'] = $availableCondition->getName();
            $condition['toolTip'] = $availableCondition->getToolTip();
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
            $condition['inputName'] = $availableCoupon->getInputName();
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
        /** @var $condition ConditionInterface */
        foreach ($conditions as $index => $condition) {
            $temp = array(
                'serviceId' => $condition->getServiceId(),
                'index' => $index,
                'name' => $condition->getName(),
                'toolTip' => $condition->getToolTip(),
                'summary' => $condition->getSummary(),
                'validators' => $condition->getValidators()
            );
            $cleanedConditions[] = $temp;
        }

        return $cleanedConditions;
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     *
     * @param string $couponServiceId Coupon service id
     *
     * @return ResponseRest
     */
    public function getBackOfficeInputsAjaxAction($couponServiceId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);
        $this->checkXmlHttpRequest();

        /** @var CouponInterface $coupon */
        $couponManager = $this->container->get($couponServiceId);

        if (!$couponManager instanceof CouponInterface) {
            $this->pageNotFound();
        }

        $response = new ResponseRest($couponManager->drawBackOfficeInputs());

        return $response;
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     *
     * @param int $couponId Coupon id
     *
     * @return ResponseRest
     */
    public function getBackOfficeConditionSummariesAjaxAction($couponId)
    {
        $this->checkAuth(AdminResources::COUPON, array(), AccessManager::VIEW);
        $this->checkXmlHttpRequest();

        /** @var Coupon $coupon */
        $coupon = CouponQuery::create()->findPk($couponId);
        if (null === $coupon) {
            return $this->pageNotFound();

        }

        /** @var CouponFactory $couponFactory */
        $couponFactory = $this->container->get('thelia.coupon.factory');
        $couponManager = $couponFactory->buildCouponFromModel($coupon);

        if (!$couponManager instanceof CouponInterface) {
            return $this->pageNotFound();
        }

        $args = array();
        $args['conditions'] = $this->cleanConditionForTemplate($couponManager->getConditions());

        return $this->render('coupon/conditions', $args);

    }

    /**
     * Add percentage logic if found in the Coupon post data
     *
     * @param array $effects            Effect parameters to populate
     * @param array $extendedInputNames Extended Inputs to manage
     *
     * @return array Populated effect with percentage
     */
    protected function addExtendedLogic(array $effects, array $extendedInputNames)
    {
        /** @var Request $request */
        $request = $this->container->get('request');
        $postData = $request->request;
        // Validate quantity input

        if ($postData->has(RemoveXPercent::INPUT_EXTENDED__NAME)) {
            $extentedPostData = $postData->get(RemoveXPercent::INPUT_EXTENDED__NAME);

            foreach ($extendedInputNames as $extendedInputName) {
                if (isset($extentedPostData[$extendedInputName])) {
                    $inputValue = $extentedPostData[$extendedInputName];
                    $effects[$extendedInputName] = $inputValue;
                }
            }
        }

        return $effects;
    }

    /**
     * Feed the Coupon Create or Update event with the User inputs
     *
     * @param Form   $form  Form containing user data
     * @param Coupon $model Model if in update mode
     *
     * @return CouponCreateOrUpdateEvent
     */
    protected function feedCouponCreateOrUpdateEvent(Form $form, Coupon $model = null)
    {
        // Get the form field values
        $data = $form->getData();
        $serviceId = $data['type'];
        /** @var CouponInterface $couponManager */
        $couponManager = $this->container->get($serviceId);
        $effects = array(CouponAbstract::INPUT_AMOUNT_NAME => $data[CouponAbstract::INPUT_AMOUNT_NAME]);
        $effects = $this->addExtendedLogic($effects, $couponManager->getExtendedInputs());

        $couponEvent = new CouponCreateOrUpdateEvent(
            $data['code'],
            $serviceId,
            $data['title'],
            $effects,
            $data['shortDescription'],
            $data['description'],
            $data['isEnabled'],
            \DateTime::createFromFormat('Y-m-d', $data['expirationDate']),
            $data['isAvailableOnSpecialOffers'],
            $data['isCumulative'],
            $data['isRemovingPostage'],
            $data['maxUsage'],
            $data['locale']
        );

        // If Update mode
        if (isset($model)) {
            $couponEvent->setCouponModel($model);
        }

        return $couponEvent;
    }

    /**
     * Build ConditionInterface from request
     *
     * @return ConditionInterface
     */
    protected function buildConditionFromRequest()
    {
        $request = $this->getRequest();
        $post = $request->request->getIterator();
        $serviceId = $request->request->get('categoryCondition');
        $operators = array();
        $values = array();
        foreach ($post as $key => $input) {
            if (isset($input['operator']) && isset($input['value'])) {
                $operators[$key] = $input['operator'];
                $values[$key] = $input['value'];
            }
        }

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->container->get('thelia.condition.factory');
        $conditionToSave = $conditionFactory->build($serviceId, $operators, $values);

        return $conditionToSave;
    }

    /**
     * Manage how a Condition is updated
     *
     * @param Coupon              $coupon     Coupon Model
     * @param ConditionCollection $conditions Condition collection
     */
    protected function manageConditionUpdate(Coupon $coupon, ConditionCollection $conditions)
    {
        $couponEvent = new CouponCreateOrUpdateEvent(
            $coupon->getCode(),
            $coupon->getType(),
            $coupon->getTitle(),
            $coupon->getEffects(),
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
    }

}
