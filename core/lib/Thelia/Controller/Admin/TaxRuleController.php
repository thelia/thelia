<?php

declare(strict_types=1);

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


use Exception;
use LogicException;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tools\URL;

class TaxRuleController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'taxrule',
            'manual',
            'order',
            AdminResources::TAX,
            TheliaEvents::TAX_RULE_CREATE,
            TheliaEvents::TAX_RULE_UPDATE,
            TheliaEvents::TAX_RULE_DELETE
        );
    }

    public function defaultAction(): \Symfony\Component\HttpFoundation\Response
    {
        // In the tax rule template we use the TaxCreationForm.
        //
        // The TaxCreationForm require the TaxEngine, but we cannot pass it from the Parser Form plugin,
        // as the container is not passed to forms by this plugin.
        //
        // So we create an instance of TaxCreationForm here (we have the container), and put it in the ParserContext.
        // This way, the Form plugin will use this instance, instead on creating it.
        $taxCreationForm = $this->createForm(AdminForm::TAX_CREATION);

        $this->getParserContext()->addForm($taxCreationForm);

        return parent::defaultAction();
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::TAX_RULE_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::TAX_RULE_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = new TaxRuleEvent();

        $event->setLocale($formData['locale']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);

        return $event;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = new TaxRuleEvent();

        $event->setLocale($formData['locale']);
        $event->setId($formData['id']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);

        return $event;
    }

    protected function getUpdateTaxListEvent(array $formData): TaxRuleEvent
    {
        $event = new TaxRuleEvent();

        $event->setId($formData['id']);
        $event->setTaxList($formData['tax_list']);
        $event->setCountryList($formData['country_list']);
        $event->setCountryDeletedList($formData['country_deleted_list']);

        return $event;
    }

    protected function getDeleteEvent(): TaxRuleEvent
    {
        $event = new TaxRuleEvent();

        $event->setId(
            $this->getRequest()->get('tax_rule_id', 0)
        );

        return $event;
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasTaxRule();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'description' => $object->getDescription(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::TAX_RULE_MODIFICATION, FormType::class, $data);
    }

    /**
     * @param TaxRule $object
     */
    protected function hydrateTaxUpdateForm($object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::TAX_RULE_TAX_LIST_UPDATE, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasTaxRule() ? $event->getTaxRule() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $taxRule = TaxRuleQuery::create()
            ->findOneById($this->getRequest()->get('tax_rule_id'));

        if (null !== $taxRule) {
            $taxRule->setLocale($this->getCurrentEditionLocale());
        }

        return $taxRule;
    }

    /**
     * @param TaxRule $object
     *
     * @return string
     */
    protected function getObjectLabel(activeRecordInterface $object): ?string    {
        return $object->getTitle();
    }

    /**
     * @param TaxRule $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function getViewArguments($country = null, $tab = null, $state = null): array
    {
        return [
            'tab' => $tab ?? $this->getRequest()->get('tab', 'data'),
            'country' => $country ?? $this->getRequest()->get('country', CountryQuery::create()->findOneByByDefault(1)->getId()),
            'state' => $state,
        ];
    }

    protected function getRouteArguments($tax_rule_id = null): array
    {
        return [
            'tax_rule_id' => $tax_rule_id ?? $this->getRequest()->get('tax_rule_id'),
        ];
    }

    protected function renderListTemplate($currentOrder): Response
    {
        // We always return to the feature edition form
        return $this->render(
            'taxes-rules',
            [
                'taxruleIdDeliveryModule' => ConfigQuery::read('taxrule_id_delivery_module'),
            ]
        );
    }

    protected function renderEditionTemplate(): Response
    {
        // We always return to the feature edition form
        return $this->render('tax-rule-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null, $state = null): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.taxes-rules.update',
            $this->getViewArguments($country, null, $state),
            $this->getRouteArguments()
        );
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param ActionEvent $createEvent the create event
     *
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction(ActionEvent $createEvent): ?\Symfony\Component\HttpFoundation\Response
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.taxes-rules.update',
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getTaxRule()->getId())
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.taxes-rules.list');
    }

    public function updateAction(ParserContext $parserContext): \Symfony\Component\HttpFoundation\Response
    {
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $object = $this->getExistingObject();

        if ($object != null) {
            // Hydrate the form abd pass it to the parser
            $changeTaxesForm = $this->hydrateTaxUpdateForm($object);

            // Pass it to the parser
            $parserContext->addForm($changeTaxesForm);
        }

        return parent::updateAction($parserContext);
    }

    public function setDefaultAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $setDefaultEvent = new TaxRuleEvent();

        $taxRuleId = $this->getRequest()->attributes->get('tax_rule_id');

        $setDefaultEvent->setId(
            $taxRuleId
        );

        $eventDispatcher->dispatch($setDefaultEvent, TheliaEvents::TAX_RULE_SET_DEFAULT);

        return $this->redirectToListTemplate();
    }

    public function processUpdateTaxesAction(EventDispatcherInterface $eventDispatcher): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $responseData = [
            'success' => false,
            'message' => '',
        ];

        $error_msg = false;

        // Create the form from the request
        $changeForm = $this->createForm(AdminForm::TAX_RULE_TAX_LIST_UPDATE);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateTaxListEvent($data);

            $eventDispatcher->dispatch($changeEvent, TheliaEvents::TAX_RULE_TAXES_UPDATE);

            if (!$this->eventContainsObject($changeEvent)) {
                throw new LogicException(
                    $this->getTranslator()->trans('No %obj was updated.', ['%obj', $this->objectName])
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        '%s %s (ID %s) modified',
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            $responseData['success'] = true;
            $responseData['data'] = $this->getSpecification(
                $changeEvent->getTaxRule()->getId()
            );

            return $this->jsonResponse(json_encode($responseData));
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext($this->getTranslator()->trans('%obj modification', ['%obj' => 'taxrule']), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        $responseData['message'] = $error_msg;

        return $this->jsonResponse(json_encode($responseData));
    }

    /**
     * @return Response
     */
    public function specsAction($taxRuleId): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->getSpecification($taxRuleId);

        return $this->jsonResponse(json_encode($data));
    }

    protected function getSpecification($taxRuleId): array
    {
        $taxRuleCountries = TaxRuleCountryQuery::create()
            ->filterByTaxRuleId($taxRuleId)
            ->orderByCountryId()
            ->orderByStateId()
            ->orderByPosition()
            ->orderByTaxId()
            ->find();

        $specKey = [];
        $specifications = [];
        $taxRules = [];

        if (!$taxRuleCountries->isEmpty()) {
            $taxRuleCountry = $taxRuleCountries->getFirst();
            $currentCountryId = $taxRuleCountry->getCountryId();
            $currentStateId = (int) $taxRuleCountry->getStateId();

            while (true) {
                $hasChanged = $taxRuleCountry === null
                    || $taxRuleCountry->getCountryId() != $currentCountryId
                    || (int) $taxRuleCountry->getStateId() !== $currentStateId
                ;

                if ($hasChanged) {
                    $specification = implode(',', $specKey);

                    $specifications[] = [
                        'country' => $currentCountryId,
                        'state' => $currentStateId,
                        'specification' => $specification,
                    ];

                    if (!\in_array($specification, $taxRules)) {
                        $taxRules[] = $specification;
                    }

                    if (null === $taxRuleCountry) {
                        break;
                    }

                    $currentCountryId = $taxRuleCountry->getCountryId();
                    $currentStateId = (int) $taxRuleCountry->getStateId();
                    $specKey = [];
                }

                $specKey[] = $taxRuleCountry->getTaxId().'-'.$taxRuleCountry->getPosition();

                $taxRuleCountry = $taxRuleCountries->getNext();
            }
        }

        return [
            'taxRules' => $taxRules,
            'specifications' => $specifications,
        ];
    }

    #[Route(
        '/admin/configuration/taxes_rules/delivery_module/update',
        name: 'admin.configuration.tax-rule.delivery.modules.update',
        methods: ['POST']
    )]
    public function updateDeliveryModulesTaxRule(): Response|RedirectResponse
    {
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $data = $this->getRequest()->request;
        $taxruleId = $data->get('delivery-module-tax-rule');

        ConfigQuery::write('taxrule_id_delivery_module', $taxruleId);

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('admin/configuration/taxes_rules'));
    }
}
