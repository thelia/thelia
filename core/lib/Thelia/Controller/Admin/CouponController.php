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
use Thelia\Constraint\ConstraintFactory;
use Thelia\Constraint\ConstraintFactoryTest;
use Thelia\Constraint\Rule\AvailableForTotalAmount;
use Thelia\Constraint\Rule\CouponRuleInterface;
use Thelia\Constraint\Validator\PriceParam;
use Thelia\Core\Event\Coupon\CouponCreateEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponAdapterInterface;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\CouponRuleCollection;
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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        // Check current user authorization
        $response = $this->checkAuth('admin.coupon.create');
        if ($response !==  null) {
            return $response;
        }

        // Parameters given to the template
        $args = array();

        $i18n = new I18n();
        /** @var Lang $lang */
        $lang = $this->getSession()->get('lang');
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
                ->format($lang->getDateFormat());
        }

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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateAction($couponId)
    {
        // Check current user authorization
        $response = $this->checkAuth('admin.coupon.update');
        if ($response !==  null) {
            return $response;
        }

        /** @var Coupon $coupon */
        $coupon = CouponQuery::create()->findOneById($couponId);
        if (!$coupon) {
            $this->pageNotFound();
        }

        // Parameters given to the template
        $args = array();

        $i18n = new I18n();
        /** @var Lang $lang */
        $lang = $this->getSession()->getLang();
        $eventToDispatch = TheliaEvents::COUPON_UPDATE;

        if ($this->getRequest()->isMethod('POST')) {
            $this->validateCreateOrUpdateForm(
                $i18n,
                $lang,
                $eventToDispatch,
                'updated',
                'update'
            );
        } else {
            // Prepare the data that will hydrate the form

            /** @var ConstraintFactory $constraintFactory */
            $constraintFactory = $this->container->get('thelia.constraint.factory');
            $rules = $constraintFactory->unserializeCouponRuleCollection(
                $coupon->getSerializedRules()
            );

            $data = array(
                'code' => $coupon->getCode(),
                'title' => $coupon->getTitle(),
                'amount' => $coupon->getAmount(),
                'effect' => $coupon->getType(),
                'shortDescription' => $coupon->getShortDescription(),
                'description' => $coupon->getDescription(),
                'isEnabled' => ($coupon->getIsEnabled() == 1),
                'expirationDate' => $coupon->getExpirationDate($lang->getDateFormat()),
                'isAvailableOnSpecialOffers' => ($coupon->getIsAvailableOnSpecialOffers() == 1),
                'isCumulative' => ($coupon->getIsCumulative() == 1),
                'isRemovingPostage' => ($coupon->getIsRemovingPostage() == 1),
                'maxUsage' => $coupon->getMaxUsage(),
                'rules' => $rules,
                'locale' => $coupon->getLocale(),
            );

            $args['rulesObject'] = array();

            /** @var CouponRuleInterface $rule */
            foreach ($rules->getRules() as $rule) {
                $args['rulesObject'][] = array(
                    'serviceId' => $rule->getServiceId(),
                    'name' => $rule->getName(),
                    'tooltip' => $rule->getToolTip(),
                    'validators' => $rule->getValidators()
                );
            }

            $args['rules'] = $this->cleanRuleForTemplate($rules);

            // Setup the object form
            $changeForm = new CouponCreationForm($this->getRequest(), 'form', $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        $args['availableCoupons'] = $this->getAvailableCoupons();
        $args['availableRules'] = $this->getAvailableRules();
        $args['urlAjaxGetRuleInput'] = $this->getRoute(
            'admin.coupon.rule.input',
            array('ruleId' => 'ruleId'),
            Router::ABSOLUTE_URL
        );

        $args['urlAjaxUpdateRules'] = $this->getRoute(
            'admin.coupon.rule.update',
            array('couponId' => $couponId),
            Router::ABSOLUTE_URL
        );

        $args['formAction'] = 'admin/coupon/update/' . $couponId;

        return $this->render('coupon-update', $args);
    }


//    /**
//     * Manage Coupons Rule creation display
//     *
//     * @param int $couponId Coupon id
//     *
//     * @return \Symfony\Component\HttpFoundation\Response
//     */
//    public function createRuleAction($couponId)
//    {
//        // Check current user authorization
//        $response = $this->checkAuth('admin.coupon.update');
//        if ($response !==  null) {
//            return $response;
//        }
//
//        /** @var Coupon $coupon */
//        $coupon = CouponQuery::create()->findOneById($couponId);
//        if (!$coupon) {
//            $this->pageNotFound();
//        }
//
//        // Parameters given to the template
//        $args = array();
//
//        $i18n = new I18n();
//        /** @var Lang $lang */
//        $lang = $this->getSession()->get('lang');
//        $eventToDispatch = TheliaEvents::COUPON_RULE_CREATE;
//
//        if ($this->getRequest()->isMethod('POST')) {
//            $this->validateCreateOrUpdateForm(
//                $i18n,
//                $lang,
//                $eventToDispatch,
//                'updated',
//                'update'
//            );
//        } else {
//            // Prepare the data that will hydrate the form
//
//            /** @var ConstraintFactory $constraintFactory */
//            $constraintFactory = $this->container->get('thelia.constraint.factory');
//
//            $data = array(
//                'code' => $coupon->getCode(),
//                'title' => $coupon->getTitle(),
//                'amount' => $coupon->getAmount(),
//                'effect' => $coupon->getType(),
//                'shortDescription' => $coupon->getShortDescription(),
//                'description' => $coupon->getDescription(),
//                'isEnabled' => ($coupon->getIsEnabled() == 1),
//                'expirationDate' => $coupon->getExpirationDate($lang->getDateFormat()),
//                'isAvailableOnSpecialOffers' => ($coupon->getIsAvailableOnSpecialOffers() == 1),
//                'isCumulative' => ($coupon->getIsCumulative() == 1),
//                'isRemovingPostage' => ($coupon->getIsRemovingPostage() == 1),
//                'maxUsage' => $coupon->getMaxUsage(),
//                'rules' => $constraintFactory->unserializeCouponRuleCollection($coupon->getSerializedRules()),
//                'locale' => $coupon->getLocale(),
//            );
//
//            /** @var CouponAdapterInterface $adapter */
//            $adapter = $this->container->get('thelia.adapter');
//            /** @var Translator $translator */
//            $translator = $this->container->get('thelia.translator');
//
//            $args['rulesObject'] = array();
//            /** @var CouponRuleInterface $rule */
//            foreach ($coupon->getRules()->getRules() as $rule) {
//                $args['rulesObject'][] = array(
//                    'name' => $rule->getName($translator),
//                    'tooltip' => $rule->getToolTip($translator),
//                    'validators' => $rule->getValidators()
//                );
//            }
//
//            $args['rules'] = $this->cleanRuleForTemplate($coupon->getRules()->getRules());
//
//            // Setup the object form
//            $changeForm = new CouponCreationForm($this->getRequest(), 'form', $data);
//
//            // Pass it to the parser
//            $this->getParserContext()->addForm($changeForm);
//        }
//
//        $args['formAction'] = 'admin/coupon/update/' . $couponId;
//
//        return $this->render(
//            'coupon-update',
//            $args
//        );
//    }



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
     * Manage Coupons read display
     *
     * @param string $ruleId Rule service id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getRuleInputAction($ruleId)
    {
        $this->checkAuth('ADMIN', 'admin.coupon.read');

        if ($this->isDebug()) {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $this->redirect(
                    $this->getRoute(
                        'admin',
                        array(),
                        Router::ABSOLUTE_URL
                    )
                );
            }
        }

        /** @var ConstraintFactory $constraintFactory */
        $constraintFactory = $this->container->get('thelia.constraint.factory');
        $inputs = $constraintFactory->getInputs($ruleId);

        if ($inputs === null) {
            return $this->pageNotFound();
        }

        return $this->render(
            'coupon/rule-input-ajax',
            array(
                'ruleId' => $ruleId,
                'inputs' => $inputs
            )
        );
    }


    /**
     * Manage Coupons read display
     *
     * @param int $couponId Coupon id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateRulesAction($couponId)
    {
        $this->checkAuth('ADMIN', 'admin.coupon.read');

        if ($this->isDebug()) {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $this->redirect(
                    $this->getRoute(
                        'admin',
                        array(),
                        Router::ABSOLUTE_URL
                    )
                );
            }
        }

        $search = CouponQuery::create();
        /** @var Coupon $coupon */
        $coupon = $search->findOneById($couponId);

        if (!$coupon) {
            return $this->pageNotFound();
        }

        $rules = new CouponRuleCollection();

        /** @var ConstraintFactory $constraintFactory */
        $constraintFactory = $this->container->get('thelia.constraint.factory');
        $rulesReceived = json_decode($this->getRequest()->get('rules'));
        foreach ($rulesReceived as $ruleReceived) {
            $rule = $constraintFactory->build(
                $ruleReceived->serviceId,
                (array) $ruleReceived->operators,
                (array) $ruleReceived->values
            );
            $rules->add(clone $rule);
        }

        $coupon->setSerializedRules(
            $constraintFactory->serializeCouponRuleCollection($rules)
        );

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
            $rules,
            $coupon->getLocale()
        );
        $couponEvent->setCoupon($coupon);

        $eventToDispatch = TheliaEvents::COUPON_RULE_UPDATE;
        // Dispatch Event to the Action
        $this->dispatch(
            $eventToDispatch,
            $couponEvent
        );

        $this->adminLogAppend(
            sprintf(
                'Coupon %s (ID %s) rules updated',
                $couponEvent->getTitle(),
                $couponEvent->getCoupon()->getId()
            )
        );

        $cleanedRules = $this->cleanRuleForTemplate($rules);

        return $this->render(
            'coupon/rules',
            array(
                'couponId' => $couponId,
                'rules' => $cleanedRules,
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

    /**
     * Validate the CreateOrUpdate form
     *
     * @param string $i18n            Local code (fr_FR)
     * @param Lang   $lang            Local variables container
     * @param string $eventToDispatch Event which will activate actions
     * @param string $log             created|edited
     * @param string $action          creation|edition
     *
     * @return $this
     */
    protected function validateCreateOrUpdateForm($i18n, $lang, $eventToDispatch, $log, $action)
    {
        // Create the form from the request
        $creationForm = new CouponCreationForm($this->getRequest());

        $message = false;
        try {
            // Check the form against constraints violations
            $form = $this->validateForm($creationForm, 'POST');

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
                new CouponRuleCollection(array()),
                $data['locale']
            );

            // Dispatch Event to the Action
            $this->dispatch(
                $eventToDispatch,
                $couponEvent
            );

            $this->adminLogAppend(
                sprintf(
                    'Coupon %s (ID ) ' . $log,
                    $couponEvent->getTitle(),
                    $couponEvent->getCoupon()->getId()
                )
            );

            $this->redirect(
                str_replace(
                    '{id}',
                    $couponEvent->getCoupon()->getId(),
                    $creationForm->getSuccessUrl()
                )
            );

        } catch (FormValidationException $e) {
            // Invalid data entered
            $message = 'Please check your input:';
            $this->logError($action, $message, $e);

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
     * Get all available rules
     *
     * @return array
     */
    protected function getAvailableRules()
    {
        /** @var CouponManager $couponManager */
        $couponManager = $this->container->get('thelia.coupon.manager');
        $availableRules = $couponManager->getAvailableRules();
        $cleanedRules = array();
        /** @var CouponRuleInterface $availableRule */
        foreach ($availableRules as $availableRule) {
            $rule = array();
            $rule['serviceId'] = $availableRule->getServiceId();
            $rule['name'] = $availableRule->getName();
            $rule['toolTip'] = $availableRule->getToolTip();
            $cleanedRules[] = $rule;
        }

        return $cleanedRules;
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
        $cleanedRules = array();
        /** @var CouponInterface $availableCoupon */
        foreach ($availableCoupons as $availableCoupon) {
            $rule = array();
            $rule['serviceId'] = $availableCoupon->getServiceId();
            $rule['name'] = $availableCoupon->getName();
            $rule['toolTip'] = $availableCoupon->getToolTip();
            $cleanedRules[] = $rule;
        }

        return $cleanedRules;
    }

    /**
     * @param $rules
     * @return array
     */
    protected function cleanRuleForTemplate($rules)
    {
        $cleanedRules = array();
        /** @var $rule CouponRuleInterface */
        foreach ($rules->getRules() as $rule) {
            $cleanedRules[] = $rule->getToolTip();
        }

        return $cleanedRules;
    }

//    /**
//     * Validation Rule creation
//     *
//     * @param string $type     Rule class type
//     * @param string $operator Rule operator (<, >, =, etc)
//     * @param array  $values   Rules values
//     *
//     * @return bool
//     */
//    protected function validateRulesCreation($type, $operator, $values)
//    {
//        /** @var CouponAdapterInterface $adapter */
//        $adapter = $this->container->get('thelia.adapter');
//        $validator = new PriceParam()
//        try {
//            $rule = new AvailableForTotalAmount($adapter, $validators);
//            $rule = new $type($adapter, $validators);
//        } catch (\Exception $e) {
//            return false;
//        }
//    }



}
