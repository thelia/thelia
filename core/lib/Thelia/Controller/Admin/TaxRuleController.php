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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\CountryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Model\TaxRuleQuery;

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

    public function defaultAction()
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

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::TAX_RULE_CREATION);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::TAX_RULE_MODIFICATION);
    }

    protected function getCreationEvent($formData)
    {
        $event = new TaxRuleEvent();

        $event->setLocale($formData['locale']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new TaxRuleEvent();

        $event->setLocale($formData['locale']);
        $event->setId($formData['id']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);

        return $event;
    }

    protected function getUpdateTaxListEvent($formData)
    {
        $event = new TaxRuleEvent();

        $event->setId($formData['id']);
        $event->setTaxList($formData['tax_list']);
        $event->setCountryList($formData['country_list']);
        $event->setCountryDeletedList($formData['country_deleted_list']);

        return $event;
    }

    protected function getDeleteEvent()
    {
        $event = new TaxRuleEvent();

        $event->setId(
            $this->getRequest()->get('tax_rule_id', 0)
        );

        return $event;
    }

    protected function eventContainsObject($event)
    {
        return $event->hasTaxRule();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'description'  => $object->getDescription(),
        );

        // Setup the object form
        return $this->createForm(AdminForm::TAX_RULE_MODIFICATION, "form", $data);
    }

    protected function hydrateTaxUpdateForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
        );

        // Setup the object form
        return $this->createForm(AdminForm::TAX_RULE_TAX_LIST_UPDATE, "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasTaxRule() ? $event->getTaxRule() : null;
    }

    protected function getExistingObject()
    {
        $taxRule = TaxRuleQuery::create()
            ->findOneById($this->getRequest()->get('tax_rule_id'));

        if (null !== $taxRule) {
            $taxRule->setLocale($this->getCurrentEditionLocale());
        }

        return $taxRule;
    }

    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments($country = null, $tab = null, $state = null)
    {
        return array(
            'tab' => $tab === null ? $this->getRequest()->get('tab', 'data') : $tab,
            'country' => $country === null ? $this->getRequest()->get('country', CountryQuery::create()->findOneByByDefault(1)->getId()) : $country,
            'state' => $state,
        );
    }

    protected function getRouteArguments($tax_rule_id = null)
    {
        return array(
            'tax_rule_id' => $tax_rule_id === null ? $this->getRequest()->get('tax_rule_id') : $tax_rule_id,
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'taxes-rules',
            array()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('tax-rule-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null, $state = null)
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.taxes-rules.update",
            $this->getViewArguments($country, null, $state),
            $this->getRouteArguments()
        );
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param  TaxRuleEvent $createEvent the create event
     * @return Response     a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction($createEvent)
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.taxes-rules.update",
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getTaxRule()->getId())
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute("admin.configuration.taxes-rules.list");
    }

    public function updateAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $object = $this->getExistingObject();

        if ($object != null) {
            // Hydrate the form abd pass it to the parser
            $changeTaxesForm = $this->hydrateTaxUpdateForm($object);

            // Pass it to the parser
            $this->getParserContext()->addForm($changeTaxesForm);
        }

        return parent::updateAction();
    }

    public function setDefaultAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $setDefaultEvent = new TaxRuleEvent();

        $taxRuleId = $this->getRequest()->attributes->get('tax_rule_id');

        $setDefaultEvent->setId(
            $taxRuleId
        );

        $this->dispatch(TheliaEvents::TAX_RULE_SET_DEFAULT, $setDefaultEvent);

        return $this->redirectToListTemplate();
    }

    public function processUpdateTaxesAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $responseData = [
            "success" => false,
            "message" => ''
        ];

        $error_msg = false;

        // Create the form from the request
        $changeForm = $this->createForm(AdminForm::TAX_RULE_TAX_LIST_UPDATE);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateTaxListEvent($data);

            $this->dispatch(TheliaEvents::TAX_RULE_TAXES_UPDATE, $changeEvent);

            if (! $this->eventContainsObject($changeEvent)) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', $this->objectName))
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        "%s %s (ID %s) modified",
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
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext($this->getTranslator()->trans("%obj modification", array('%obj' => 'taxrule')), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        $responseData["message"] = $error_msg;

        return $this->jsonResponse(json_encode($responseData));
    }


    /**
     * @return Response
     */
    public function specsAction($taxRuleId)
    {
        $data = $this->getSpecification($taxRuleId);

        return $this->jsonResponse(json_encode($data));
    }

    protected function getSpecification($taxRuleId)
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
            $currentStateId = intval($taxRuleCountry->getStateId());

            while (true) {
                $hasChanged = $taxRuleCountry === null
                    || $taxRuleCountry->getCountryId() != $currentCountryId
                    || intval($taxRuleCountry->getStateId()) != $currentStateId
                ;

                if ($hasChanged) {
                    $specification = implode(',', $specKey);

                    $specifications[] = [
                        'country' => $currentCountryId,
                        'state' => $currentStateId,
                        'specification' => $specification
                    ];

                    if (!in_array($specification, $taxRules)) {
                        $taxRules[] = $specification;
                    }

                    if (null === $taxRuleCountry) {
                        break;
                    }

                    $currentCountryId = $taxRuleCountry->getCountryId();
                    $currentStateId = intval($taxRuleCountry->getStateId());
                    $specKey = [];
                }

                $specKey[] = $taxRuleCountry->getTaxId() . '-' . $taxRuleCountry->getPosition();

                $taxRuleCountry = $taxRuleCountries->getNext();
            }

        }

        $data = [
            'taxRules' => $taxRules,
            'specifications' => $specifications,
        ];

        return $data;
    }
}
