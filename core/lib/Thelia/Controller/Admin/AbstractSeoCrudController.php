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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;

/**
 * Extend abstract CRUD controller to manage basic CRUD + SEO operations on a given object.
 *
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
abstract class AbstractSeoCrudController extends AbstractCrudController
{
    // Events
    protected $updateSeoEventIdentifier;

    /**
     * @param string $objectName                      the lower case object name. Example. "message"
     * @param string $defaultListOrder                the default object list order, or null if list is not sortable. Example: manual
     * @param string $orderRequestParameterName       Name of the request parameter that set the list order (null if list is not sortable)
     * @param string $resourceCode                    the 'resource' code. Example: "admin.configuration.message"
     * @param string $createEventIdentifier           the dispatched create TheliaEvent identifier. Example: TheliaEvents::MESSAGE_CREATE
     * @param string $updateEventIdentifier           the dispatched update TheliaEvent identifier. Example: TheliaEvents::MESSAGE_UPDATE
     * @param string $deleteEventIdentifier           the dispatched delete TheliaEvent identifier. Example: TheliaEvents::MESSAGE_DELETE
     * @param string $visibilityToggleEventIdentifier the dispatched visibility toggle TheliaEvent identifier, or null if the object has no visible options. Example: TheliaEvents::MESSAGE_TOGGLE_VISIBILITY
     * @param string $changePositionEventIdentifier   the dispatched position change TheliaEvent identifier, or null if the object has no position. Example: TheliaEvents::MESSAGE_UPDATE_POSITION
     * @param string $updateSeoEventIdentifier        the dispatched update SEO change TheliaEvent identifier, or null if the object has no SEO. Example: TheliaEvents::MESSAGE_UPDATE_SEO
     * @param string $moduleCode                      The module code for ACL
     */
    public function __construct(
        $objectName,
        $defaultListOrder,
        $orderRequestParameterName,
        $resourceCode,
        $createEventIdentifier,
        $updateEventIdentifier,
        $deleteEventIdentifier,
        $visibilityToggleEventIdentifier = null,
        $changePositionEventIdentifier = null,
        $updateSeoEventIdentifier = null,
        $moduleCode = null
    ) {
        parent::__construct(
            $objectName,
            $defaultListOrder,
            $orderRequestParameterName,
            $resourceCode,
            $createEventIdentifier,
            $updateEventIdentifier,
            $deleteEventIdentifier,
            $visibilityToggleEventIdentifier,
            $changePositionEventIdentifier,
            $moduleCode
        );

        $this->updateSeoEventIdentifier = $updateSeoEventIdentifier;
    }

    /**
     * Put in this method post object update SEO processing if required.
     *
     * @param UpdateSeoEvent $updateSeoEvent the update event
     *
     * @return Response|null a response, or null to continue normal processing
     */
    protected function performAdditionalUpdateSeoAction($updateSeoEvent)
    {
        return null;
    }

    /**
     * Return the update SEO form for this object.
     */
    protected function getUpdateSeoForm()
    {
        return $this->createForm(AdminForm::SEO);
    }

    /**
     * Creates the update SEO event with the provided form data.
     *
     * @param $formData
     *
     * @return UpdateSeoEvent
     */
    protected function getUpdateSeoEvent($formData)
    {
        $updateSeoEvent = new UpdateSeoEvent($formData['id']);

        $updateSeoEvent
            ->setLocale($formData['locale'])
            ->setMetaTitle($formData['meta_title'])
            ->setMetaDescription($formData['meta_description'])
            ->setMetaKeywords($formData['meta_keywords'])
            ->setUrl($formData['url'])
        ;

        // Create and dispatch the change event
        return $updateSeoEvent;
    }

    /**
     * Hydrate the SEO form for this object, before passing it to the update template.
     */
    protected function hydrateSeoForm(ParserContext $parserContext, $object)
    {
        // The "SEO" tab form
        $locale = $object->getLocale();
        $data = [
            'id' => $object->getId(),
            'locale' => $locale,
            'url' => $object->getRewrittenUrl($locale),
            'meta_title' => $object->getMetaTitle(),
            'meta_description' => $object->getMetaDescription(),
            'meta_keywords' => $object->getMetaKeywords(),
        ];

        $seoForm = $this->createForm(AdminForm::SEO, FormType::class, $data);
        $parserContext->addForm($seoForm);

        // URL based on the language
        $parserContext->set('url_language', $this->getUrlLanguage($locale));
    }

    /**
     * Update SEO modification, and either go back to the object list, or stay on the edition page.
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function processUpdateSeoAction(
        Request $request,
        ParserContext $parserContext,
        EventDispatcherInterface $eventDispatcher
    ) {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) {
            return $response;
        }

        // Error (Default: false)
        $error_msg = false;

        // Create the Form from the request
        $updateSeoForm = $this->getUpdateSeoForm();

        // Pass the object id to the request
        $request->attributes->set($this->objectName.'_id', $request->get('current_id'));

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($updateSeoForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            // Create a new event object with the modified fields
            $updateSeoEvent = $this->getUpdateSeoEvent($data);

            // Dispatch Update SEO Event
            $eventDispatcher->dispatch($updateSeoEvent, $this->updateSeoEventIdentifier);

            // Execute additional Action
            $response = $this->performAdditionalUpdateSeoAction($updateSeoEvent);

            if ($response == null) {
                // If we have to stay on the same page, do not redirect to the successUrl,
                // just redirect to the edit page again.
                if ($request->get('save_mode') == 'stay') {
                    return $this->redirectToEditionTemplate();
                }

                // Redirect to the success URL
                return $this->generateSuccessRedirect($updateSeoForm);
            }

            return $response;
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
            /*} catch (\Exception $ex) {
                // Any other error
                $error_msg = $ex->getMessage();*/
        }

        // Load object if exist
        if (null !== $object = $this->getExistingObject()) {
            // Hydrate the form abd pass it to the parser
            $changeForm = $this->hydrateObjectForm($parserContext, $object);

            // Pass it to the parser
            $parserContext->addForm($changeForm);
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans('%obj SEO modification', ['%obj' => $this->objectName]),
                $errorMessage,
                $updateSeoForm,
                $ex
            );

            // At this point, the form has errors, and should be redisplayed.
            return $this->renderEditionTemplate();
        }
    }
}
