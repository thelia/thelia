<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Coupon\CouponFactory;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;
use ContactOptionBuilder\Controller\AdminController;
use EasyCoupnManager\Event\BeforeFilterEvent;
use EasyCouponManager\EasyCouponManager;
use Procity\Controller\Back\ProductController;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Core\HttpFoundation\JsonResponse;

/**
 * Control View and Action (Model) via Events.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponController extends BaseAdminController
{
    /**
     * Datatable and search on coupons.
     *
     * @author Arthur LASHERMES <alashermes@openstudio.fr>
     */

    /**
     * @param Request $request
     * @param CouponQuery $query
     * @return void
     */
    protected function applySearchCoupon(Request $request, CouponQuery $query)
    {
        $value = $this->getSearchValue($request, 'searchCoupon');
        $query->where(CouponTableMap::COL_ID . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);

        if (strlen($value) > 2) {
            $query->_or()->where(CouponTableMap::COL_CODE . ' LIKE ?', '%' . $value . '%', \PDO::PARAM_STR);
        }
    }

    /**
     * @param Request $request
     * @param $searchKey
     * @return string
     */
    protected function getSearchValue(Request $request, $searchKey)
    {
        if(!array_key_exists('searchTypeCoupon', $_REQUEST)){
            return null;
        }
        return (string)$request->get($searchKey)['value'];
    }

    /**
     * @param $withPrivateData
     * @return array[]
     */
    protected function defineColumnsDefinition($withPrivateData = false): array
    {
        $i = -1;

        $definitions = [
            [
                'name' => 'id',
                'targets' => ++$i,
                'orm' => CouponTableMap::COL_ID,
                'title' => 'id',
            ],
            [
                'name' => 'code',
                'targets' => ++$i,
                'orm' => CouponTableMap::COL_CODE,
                'title' => 'code',
            ],
            [
                'name' => 'title',
                'targets' => ++$i,
                'orderable' => false,
                'title' => 'titre',
            ],
            [
                'name' => 'is_enabled',
                'targets' => ++$i,
                'orm' => CouponTableMap::COL_IS_ENABLED,
                'title' => 'Etat',
            ],
            [
                'name' => 'start_date',
                'targets' => ++$i,
                'orm' => CouponTableMap::COL_START_DATE,
                'title' => 'Date de début',
            ],
            [
                'name' => 'expiration_date',
                'targets' => ++$i,
                'orm' => CouponTableMap::COL_EXPIRATION_DATE,
                'title' => 'Date de fin',
            ],
            [
                'name' => 'days_before_expiration',
                'targets' => ++$i,
                'title' => 'Jour avant expiration',
                'orderable' => false,
            ],
            [
                'name' => 'is_used',
                'targets' => ++$i,
                'title' => 'utilisation',
                'orm' => CouponTableMap::COL_IS_USED,
            ],
            [
                'name' => 'action',
                'targets' => ++$i,
                'title' => 'Action',
                'orderable' => false,
            ]
        ];

        if (!$withPrivateData) {
            foreach ($definitions as &$definition) {
                unset($definition['orm']);
            }
        }

        return $definitions;
    }

    /**
     * @param $request
     * @param CouponQuery $query
     * @return void
     */
    public function searchWithCouponType($request, CouponQuery $query)
    {

        $value = $this->getSearchValue($request, 'searchTypeCoupon');
        $query->filterByType($value);
    }

    /**
     * @param Request $request
     * @return int
     */
    protected function getDraw(Request $request): int
    {
        return (int)$request->get('draw');
    }

    /**
     * Manage Coupons list display.
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function browseAction(RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::VIEW)) {
            return $response;
        }

        $request = $requestStack->getCurrentRequest();

        if ($request->isXmlHttpRequest()) {

            $query = CouponQuery::create();

            $queryCount = clone $query;

            $value = $this->getSearchValue($request, 'searchTypeCoupon');
            if($value !== ""){
                $this->searchWithCouponType($request,$query);
            }
            $this->applySearchCoupon($request, $query);

            $querySearchCount = clone $query;

            $coupons = $query->limit(25)->find();


            $json = [
                "draw" => $this->getDraw($request),
                "recordsTotal" => $queryCount->count(),
                "recordsFiltered" => $querySearchCount->count(),
                "data" => [],
                "coupon" => count($coupons->getData()),
            ];


            foreach ($coupons as $coupon) {

                $updateUrl = URL::getInstance()->absoluteUrl('admin/coupon/update/' . $coupon->getId());

                $days_after_expiration = $coupon->getExpirationDate()->diff($coupon->getStartDate());
                $days_after_expiration = $days_after_expiration->days;


                $json['data'][] = [
                    [
                        'id' => $coupon->getId(),
                        'href' => $updateUrl
                    ],
                    [
                        $coupon->getCode()
                    ],
                    [
                        $coupon->getTitle()
                    ],
                    [
                        $coupon->getIsEnabled()
                    ],
                    [
                        $coupon->getStartDate()->format('d/m/y H:i:s')
                    ],
                    [
                        $coupon->getExpirationDate()->format('d/m/y H:i:s')
                    ],
                    [
                        $days_after_expiration
                    ],
                    [
                        $coupon->getIsUsed()
                    ],
                    [
                        'coupon_id' => $coupon->getId(),
                        'hrefUpdate' => $updateUrl,
                    ]
                ];
            }

            return new JsonResponse($json);

        }

        $args['availableCoupons'] = $this->getAvailableCoupons();

        return $this->render('coupon-list', [
            'columnsDefinition' => $this->defineColumnsDefinition(),
            'theliaVersion' => Thelia::THELIA_VERSION,
            'availableCoupons' => $args['availableCoupons'],
            'name' => $args['availableCoupons'][0]['name'],

        ]);

    }

    /**
     * Manage Coupons creation display.
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function createAction(EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::CREATE)) {
            return $response;
        }

        // Parameters given to the template
        $args = [];

        $eventToDispatch = TheliaEvents::COUPON_CREATE;

        if ($this->getRequest()->isMethod('POST')) {
            if (null !== $response = $this->validateCreateOrUpdateForm(
                $eventDispatcher,
                $eventToDispatch,
                'created',
                'creation'
            )) {
                return $response;
            }
        } else {
            // If no input for expirationDate, now + 2 months
            $defaultDate = new \DateTime();
            $args['nowDate'] = $defaultDate->format($this->getDefaultDateFormat());
            $args['defaultDate'] = $defaultDate->modify('+2 month')->format($this->getDefaultDateFormat());
        }

        $args['dateFormat'] = $this->getDefaultDateFormat();
        $args['availableCoupons'] = $this->getAvailableCoupons();
        $args['urlAjaxAdminCouponDrawInputs'] = $this->getRoute(
            'admin.coupon.draw.inputs.ajax',
            ['couponServiceId' => 'couponServiceId'],
            Router::ABSOLUTE_URL
        );
        $args['formAction'] = 'admin/coupon/create';

        // Setup empty data if form is already in parser context
        $this->getParserContext()->addForm($this->createForm(AdminForm::COUPON_CREATION));

        return $this->render(
            'coupon-create',
            $args
        );
    }

    /**
     * Manage Coupons edition display.
     *
     * @param int $couponId Coupon id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function updateAction(EventDispatcherInterface $eventDispatcher, $couponId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::UPDATE)) {
            return $response;
        }

        /** @var Coupon $coupon */
        $coupon = CouponQuery::create()->findPk($couponId);
        if (null === $coupon) {
            return $this->pageNotFound();
        }

        $coupon->setLocale($this->getCurrentEditionLocale());

        /** @var CouponFactory $couponFactory */
        $couponFactory = $this->container->get('thelia.coupon.factory');
        $couponManager = $couponFactory->buildCouponFromModel($coupon);

        // Parameters given to the template
        $args = [];

        $eventToDispatch = TheliaEvents::COUPON_UPDATE;

        // Update
        if ($this->getRequest()->isMethod('POST')) {
            if (null !== $response = $this->validateCreateOrUpdateForm(
                $eventDispatcher,
                $eventToDispatch,
                'updated',
                'update',
                $coupon
            )) {
                return $response;
            }
        } else {
            // Display
            // Prepare the data that will hydrate the form
            /** @var ConditionFactory $conditionFactory */
            $conditionFactory = $this->container->get('thelia.condition.factory');
            $conditions = $conditionFactory->unserializeConditionCollection(
                $coupon->getSerializedConditions()
            );

            $freeShippingForCountries = $freeShippingForModules = [];

            /** @var CouponCountry $item */
            foreach ($coupon->getFreeShippingForCountries() as $item) {
                $freeShippingForCountries[] = $item->getCountryId();
            }

            /** @var CouponModule $item */
            foreach ($coupon->getFreeShippingForModules() as $item) {
                $freeShippingForModules[] = $item->getModuleId();
            }

            if (empty($freeShippingForCountries)) {
                $freeShippingForCountries[] = 0;
            }
            if (empty($freeShippingForModules)) {
                $freeShippingForModules[] = 0;
            }

            $data = [
                'code' => $coupon->getCode(),
                'title' => $coupon->getTitle(),
                'amount' => $coupon->getAmount(),
                'type' => $coupon->getType(),
                'shortDescription' => $coupon->getShortDescription(),
                'description' => $coupon->getDescription(),
                'isEnabled' => $coupon->getIsEnabled(),
                'startDate' => $coupon->getStartDate($this->getDefaultDateFormat()),
                'expirationDate' => $coupon->getExpirationDate($this->getDefaultDateFormat()),
                'isAvailableOnSpecialOffers' => $coupon->getIsAvailableOnSpecialOffers(),
                'isCumulative' => $coupon->getIsCumulative(),
                'isRemovingPostage' => $coupon->getIsRemovingPostage(),
                'maxUsage' => $coupon->getMaxUsage(),
                'conditions' => $conditions,
                'locale' => $this->getCurrentEditionLocale(),
                'freeShippingForCountries' => $freeShippingForCountries,
                'freeShippingForModules' => $freeShippingForModules,
                'perCustomerUsageCount' => $coupon->getPerCustomerUsageCount(),
            ];

            $args['conditions'] = $this->cleanConditionForTemplate($conditions);

            // Setup the object form
            $changeForm = $this->createForm(AdminForm::COUPON_CREATION, FormType::class, $data);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeForm);
        }

        $args['couponCode'] = $coupon->getCode();
        $args['couponType'] = $coupon->getType();
        $args['availableCoupons'] = $this->getAvailableCoupons();
        $args['couponInputsHtml'] = $couponManager->drawBackOfficeInputs();
        $args['urlAjaxAdminCouponDrawInputs'] = $this->getRoute(
            'admin.coupon.draw.inputs.ajax',
            ['couponServiceId' => 'couponServiceId'],
            Router::ABSOLUTE_URL
        );
        $args['availableConditions'] = $this->getAvailableConditions();
        $args['urlAjaxGetConditionInputFromServiceId'] = $this->getRoute(
            'admin.coupon.draw.condition.read.inputs.ajax',
            ['conditionId' => 'conditionId'],
            Router::ABSOLUTE_URL
        );
        $args['urlAjaxGetConditionInputFromConditionInterface'] = $this->getRoute(
            'admin.coupon.draw.condition.update.inputs.ajax',
            [
                'couponId' => $couponId,
                'conditionIndex' => 8888888,
            ],
            Router::ABSOLUTE_URL
        );

        $args['urlAjaxSaveConditions'] = $this->getRoute(
            'admin.coupon.condition.save',
            ['couponId' => $couponId],
            Router::ABSOLUTE_URL
        );
        $args['urlAjaxDeleteConditions'] = $this->getRoute(
            'admin.coupon.condition.delete',
            [
                'couponId' => $couponId,
                'conditionIndex' => 8888888,
            ],
            Router::ABSOLUTE_URL
        );
        $args['urlAjaxGetConditionSummaries'] = $this->getRoute(
            'admin.coupon.draw.condition.summaries.ajax',
            ['couponId' => $couponId],
            Router::ABSOLUTE_URL
        );

        $args['formAction'] = 'admin/coupon/update/'.$couponId;

        $args['dateFormat'] = $this->getDefaultDateFormat();

        $args['couponId'] = $couponId;

        return $this->render('coupon-update', $args);
    }

    /**
     * Manage Coupons read display.
     *
     * @param string $conditionId Condition service id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function getConditionEmptyInputAjaxAction($conditionId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::VIEW)) {
            return $response;
        }

        $this->checkXmlHttpRequest();

        if (!empty($conditionId)) {
            /** @var ConditionFactory $conditionFactory */
            $conditionFactory = $this->container->get('thelia.condition.factory');
            $inputs = $conditionFactory->getInputsFromServiceId($conditionId);

            if (!$this->container->has($conditionId)) {
                return false;
            }

            if ($inputs === null) {
                return $this->pageNotFound();
            }

            /** @var ConditionInterface $condition */
            $condition = $this->container->get($conditionId);

            $html = $condition->drawBackOfficeInputs();
            $serviceId = $condition->getServiceId();
        } else {
            $html = '';
            $serviceId = '';
        }

        return $this->render(
            'coupon/condition-input-ajax',
            [
                'inputsDrawn' => $html,
                'conditionServiceId' => $serviceId,
                'conditionIndex' => '',
            ]
        );
    }

    /**
     * Manage Coupons read display.
     *
     * @param int $couponId       Coupon id being updated
     * @param int $conditionIndex Coupon Condition position in the collection
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function getConditionToUpdateInputAjaxAction($couponId, $conditionIndex)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::VIEW)) {
            return $response;
        }

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
            [
                'inputsDrawn' => $condition->drawBackOfficeInputs(),
                'conditionServiceId' => $condition->getServiceId(),
                'conditionIndex' => $conditionIndex,
            ]
        );
    }

    /**
     * Manage Coupons read display.
     *
     * @param int $couponId Coupon id
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function saveConditionsAction(EventDispatcherInterface $eventDispatcher, $couponId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::UPDATE)) {
            return $response;
        }

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

        $this->manageConditionUpdate($eventDispatcher, $coupon, $conditions);

        return new Response();
    }

    /**
     * Manage Coupons condition deleteion.
     *
     * @param int $couponId       Coupon id
     * @param int $conditionIndex Coupon condition index in the collection
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function deleteConditionsAction(EventDispatcherInterface $eventDispatcher, $couponId, $conditionIndex)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::UPDATE)) {
            return $response;
        }

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

        $this->manageConditionUpdate($eventDispatcher, $coupon, $conditions);

        return new Response();
    }

    /**
     * Log error message.
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
                'Error during Coupon '.$action.' process : %s. Exception was %s',
                $message,
                $e->getMessage()
            )
        );

        return $this;
    }

    /**
     * Validate the CreateOrUpdate form.
     *
     * @param string $eventToDispatch Event which will activate actions
     * @param string $log             created|edited
     * @param string $action          creation|edition
     * @param Coupon $model           Model if in update mode
     *
     * @return $this
     */
    protected function validateCreateOrUpdateForm(EventDispatcherInterface $eventDispatcher, $eventToDispatch, $log, $action, Coupon $model = null)
    {
        // Create the form from the request
        $couponForm = $this->getForm($action, $model);
        $response = null;
        $message = false;
        try {
            // Check the form against conditions violations
            $form = $this->validateForm($couponForm, 'POST');

            $couponEvent = $this->feedCouponCreateOrUpdateEvent($form, $model);

            // Dispatch Event to the Action
            $eventDispatcher->dispatch(
                $couponEvent,
                $eventToDispatch
            );

            $this->adminLogAppend(
                AdminResources::COUPON,
                AccessManager::UPDATE,
                sprintf(
                    'Coupon %s (ID ) '.$log,
                    $couponEvent->getTitle(),
                    $couponEvent->getCouponModel()->getId()
                ),
                $couponEvent->getCouponModel()->getId()
            );

            if ($this->getRequest()->get('save_mode') == 'stay') {
                $response = new RedirectResponse(str_replace(
                    '{id}',
                    $couponEvent->getCouponModel()->getId(),
                    $couponForm->getSuccessUrl()
                ));
            } else {
                // Redirect to the success URL
                $response = new RedirectResponse(
                    URL::getInstance()->absoluteUrl($this->getRoute('admin.coupon.list'))
                );
            }
        } catch (FormValidationException $ex) {
            // Invalid data entered
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $message = $this->getTranslator()->trans('Sorry, an error occurred: %err', ['%err' => $ex->getMessage()]);

            $this->logError($action, $message, $ex);
        }

        if ($message !== false) {
            // Mark the form as with error
            $couponForm->setErrorMessage($message);

            // Send the form and the error to the parser
            $this->getParserContext()
                ->addForm($couponForm)
                ->setGeneralError($message);
        }

        return $response;
    }

    /**
     * Get all available conditions.
     *
     * @return array
     */
    protected function getAvailableConditions()
    {
        /** @var CouponManager $couponManager */
        $couponManager = $this->container->get('thelia.coupon.manager');
        $availableConditions = $couponManager->getAvailableConditions();
        $cleanedConditions = [];
        /** @var ConditionInterface $availableCondition */
        foreach ($availableConditions as $availableCondition) {
            $condition = [];
            $condition['serviceId'] = $availableCondition->getServiceId();
            $condition['name'] = $availableCondition->getName();
            $condition['toolTip'] = $availableCondition->getToolTip();
            $cleanedConditions[] = $condition;
        }

        return $cleanedConditions;
    }

    /**
     * Get all available coupons.
     *
     * @return array
     */
    protected function getAvailableCoupons()
    {
        /** @var CouponManager $couponManager */
        $couponManager = $this->container->get('thelia.coupon.manager');
        $availableCoupons = $couponManager->getAvailableCoupons();
        $cleanedCoupons = [];
        /** @var CouponInterface $availableCoupon */
        foreach ($availableCoupons as $availableCoupon) {
            $condition = [];
            $condition['serviceId'] = $availableCoupon->getServiceId();
            $condition['name'] = $availableCoupon->getName();
            $condition['toolTip'] = $availableCoupon->getToolTip();

            $cleanedCoupons[] = $condition;
        }

        return $cleanedCoupons;
    }

    /**
     * Clean condition for template.
     *
     * @param ConditionCollection $conditions Condition collection
     *
     * @return array
     */
    protected function cleanConditionForTemplate(ConditionCollection $conditions)
    {
        $cleanedConditions = [];
        /** @var $condition ConditionInterface */
        foreach ($conditions as $index => $condition) {
            $temp = [
                'serviceId' => $condition->getServiceId(),
                'index' => $index,
                'name' => $condition->getName(),
                'toolTip' => $condition->getToolTip(),
                'summary' => $condition->getSummary(),
                'validators' => $condition->getValidators(),
            ];
            $cleanedConditions[] = $temp;
        }

        return $cleanedConditions;
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect.
     *
     * @param string $couponServiceId Coupon service id
     *
     * @return ResponseRest
     */
    public function getBackOfficeInputsAjaxAction($couponServiceId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::VIEW)) {
            return $response;
        }

        if (!empty($couponServiceId)) {
            $this->checkXmlHttpRequest();

            /** @var CouponInterface $coupon */
            $couponManager = $this->container->get($couponServiceId);

            if (!$couponManager instanceof CouponInterface) {
                $this->pageNotFound();
            }

            $response = new ResponseRest($couponManager->drawBackOfficeInputs(), 'text');
        } else {
            // Return an empty response if the service ID is not defined
            // Typically, when the user chooses "Please select a coupon type"
            $response = new ResponseRest('');
        }

        return $response;
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect.
     *
     * @param int $couponId Coupon id
     *
     * @return ResponseRest
     */
    public function getBackOfficeConditionSummariesAjaxAction($couponId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::VIEW)) {
            return $response;
        }

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

        $args = [];
        $args['conditions'] = $this->cleanConditionForTemplate($couponManager->getConditions());

        return $this->render('coupon/conditions', $args);
    }

    /**
     * Feed the Coupon Create or Update event with the User inputs.
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

        /** @var CouponInterface $coupon */
        $coupon = $this->container->get($serviceId);

        $couponEvent = new CouponCreateOrUpdateEvent(
            $data['code'],
            $serviceId,
            $data['title'],
            $coupon->getEffects($data),
            $data['shortDescription'],
            $data['description'],
            $data['isEnabled'],
            \DateTime::createFromFormat($this->getDefaultDateFormat(), $data['expirationDate']),
            $data['isAvailableOnSpecialOffers'],
            $data['isCumulative'],
            $data['isRemovingPostage'],
            $data['maxUsage'],
            $data['locale'],
            $data['freeShippingForCountries'],
            $data['freeShippingForModules'],
            $data['perCustomerUsageCount'],
            empty($data['startDate']) ? null : \DateTime::createFromFormat($this->getDefaultDateFormat(), $data['startDate'])
        );

        // If Update mode
        if (isset($model)) {
            $couponEvent->setCouponModel($model);
        }

        return $couponEvent;
    }

    /**
     * Build ConditionInterface from request.
     *
     * @return ConditionInterface
     */
    protected function buildConditionFromRequest()
    {
        $request = $this->getRequest();
        $post = $request->request->getIterator();
        $serviceId = $request->request->get('categoryCondition');
        $operators = [];
        $values = [];
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
     * Manage how a Condition is updated.
     *
     * @param Coupon              $coupon     Coupon Model
     * @param ConditionCollection $conditions Condition collection
     */
    protected function manageConditionUpdate(EventDispatcherInterface $eventDispatcher, Coupon $coupon, ConditionCollection $conditions): void
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
            $coupon->getLocale(),
            $coupon->getFreeShippingForCountries(),
            $coupon->getFreeShippingForModules(),
            $coupon->getPerCustomerUsageCount(),
            $coupon->getStartDate()
        );
        $couponEvent->setCouponModel($coupon);
        $couponEvent->setConditions($conditions);

        $eventToDispatch = TheliaEvents::COUPON_CONDITION_UPDATE;
        // Dispatch Event to the Action
        $eventDispatcher->dispatch(
            $couponEvent,
            $eventToDispatch
        );

        $this->adminLogAppend(
            AdminResources::COUPON,
            AccessManager::UPDATE,
            sprintf(
                'Coupon %s (ID %s) conditions updated',
                $couponEvent->getCouponModel()->getTitle(),
                $couponEvent->getCouponModel()->getType()
            ),
            $couponEvent->getCouponModel()->getId()
        );
    }

    protected function getDefaultDateFormat()
    {
        return LangQuery::create()->findOneByByDefault(true)->getDatetimeFormat();
    }

    /**
     * @param string      $action
     * @param Coupon|null $coupon
     *
     * @return \Thelia\Form\BaseForm
     */
    protected function getForm($action, $coupon)
    {
        $data = [];

        if (null !== $coupon) {
            $data['code'] = $coupon->getCode();
        }

        return $this->createForm(AdminForm::COUPON_CREATION, FormType::class, $data, [
            'validation_groups' => ['Default', $action],
        ]);
    }

    public function deleteAction(EventDispatcherInterface $eventDispatcher)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth(AdminResources::COUPON, [], AccessManager::DELETE)) {
            return $response;
        }

        try {
            // Check token
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get('_token')
            );

            // Retrieve coupon
            $coupon = CouponQuery::create()
                ->findPk($couponId = $this->getRequest()->request->get('coupon_id'))
            ;
            $deleteEvent = new CouponDeleteEvent($coupon);

            $eventDispatcher->dispatch($deleteEvent, TheliaEvents::COUPON_DELETE);

            if (null !== $deletedObject = $deleteEvent->getCoupon()) {
                $this->adminLogAppend(
                    AdminResources::COUPON,
                    AccessManager::DELETE,
                    sprintf(
                        'Coupon %s (ID %s) deleted',
                        $deletedObject->getCode(),
                        $deletedObject->getId()
                    ),
                    $deletedObject->getId()
                );
            }

            return new RedirectResponse(
                URL::getInstance()->absoluteUrl($this->getRoute('admin.coupon.list'))
            );
        } catch (\Exception $e) {
            $this->getParserContext()
                ->setGeneralError($e->getMessage())
            ;

            return $this->browseAction();
        }
    }
}
