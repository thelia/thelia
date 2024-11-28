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

namespace Thelia\Core\Template;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Form\TheliaFormValidator;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Thelia;
use Thelia\Form\BaseForm;
use TheliaSmarty\Template\Exception\SmartyPluginException;

/**
 * The parser context is an application-wide context, which stores var-value pairs.
 * Theses pairs are injected in the parser and becomes available to the templates.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ParserContext implements \IteratorAggregate
{
    // Lifetime, in seconds, of form error data
    public const FORM_ERROR_LIFETIME_SECONDS = 60;

    private $formStore = [];
    private $store = [];

    /** @var TheliaFormFactory */
    private $formFactory;

    /** @var TheliaFormValidator */
    private $formValidator;

    /** @var RequestStack */
    private $requestStack;

    public function __construct(
        RequestStack $requestStack,
        TheliaFormFactory $formFactory,
        TheliaFormValidator $formValidator
    ) {
        // Setup basic variables
        $this->set('THELIA_VERSION', Thelia::THELIA_VERSION);

        $this->requestStack = $requestStack;
        $this->formFactory = $formFactory;
        $this->formValidator = $formValidator;

        // Purge outdated error form contexts
        if (null !== $this->requestStack->getCurrentRequest()) {
            $this->cleanOutdatedFormErrorInformation();
        }
    }

    /**
     * Set the current form.
     *
     * @return $this
     */
    public function pushCurrentForm(BaseForm $form): self
    {
        $this->formStore[] = $form;

        return $this;
    }

    /**
     * Set the current form.
     *
     * @param BaseForm|null $default
     *
     * @return BaseForm|null
     */
    public function popCurrentForm($default = null)
    {
        $form = array_pop($this->formStore);

        if (null === $form) {
            return $default;
        }

        return $form;
    }

    public function getCurrentForm()
    {
        $form = end($this->formStore);

        if (false === $form) {
            throw new SmartyPluginException(
                'There is currently no defined form'
            );
        }

        return $form;
    }

    // -- Error form -----------------------------------------------------------

    /**
     * Remove all objects in data, because they are probably not serializable.
     *
     * @return array
     */
    protected function cleanFormData(array $data)
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->cleanFormData($value);
            } elseif (\is_object($value)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Add a new form to the error form context.
     *
     * @param BaseForm $form the errored form
     *
     * @return $this
     */
    public function addForm(BaseForm $form): self
    {
        $formErrorInformation = $this->getSession()->getFormErrorInformation();

        // Get form field error details
        $formFieldErrors = [];

        /** @var Form $field */
        foreach ($form->getForm()->getIterator() as $field) {
            $errors = $field->getErrors();

            if (\count($errors) > 0) {
                $formFieldErrors[$field->getName()] = [];

                /** @var FormError $error */
                foreach ($errors as $error) {
                    $formFieldErrors[$field->getName()][] = [
                        'message' => $error->getMessage(),
                        'template' => $error->getMessageTemplate(),
                        'parameters' => $error->getMessageParameters(),
                        'pluralization' => $error->getMessagePluralization(),
                    ];
                }
            }
        }

        $this->set($form::class.':'.$form->getType(), $form);

        // Set form error information
        $formErrorInformation[$form::class.':'.$form->getType()] = [
            'data' => $this->cleanFormData($form->getForm()->getData()),
            'hasError' => $form->hasError(),
            'errorMessage' => $form->getErrorMessage(),
            'method' => $this->requestStack->getCurrentRequest()->getMethod(),
            'timestamp' => time(),
            'validation_groups' => $form->getForm()->getConfig()->getOption('validation_groups'),
            'field_errors' => $formFieldErrors,
        ];

        $this->getSession()->setFormErrorInformation($formErrorInformation);

        return $this;
    }

    /**
     * Check if the specified form has errors, and return an instance of this form if it's the case.
     *
     * @param string $formId    the form ID, as defined in the container
     * @param string $formClass the form full qualified class name
     * @param string $formType  the form type, something like 'form'
     *
     * @return BaseForm|null null if no error information is available
     */
    public function getForm($formId, $formClass, $formType)
    {
        if (isset($this->store[$formClass.':'.$formType]) && $this->store[$formClass.':'.$formType] instanceof BaseForm) {
            return $this->store[$formClass.':'.$formType];
        }

        $formErrorInformation = $this->getSession()->getFormErrorInformation();

        if (isset($formErrorInformation[$formClass.':'.$formType])) {
            $formInfo = $formErrorInformation[$formClass.':'.$formType];

            if (\is_array($formInfo['data'])) {
                $form = $this->formFactory->createForm(
                    $formId,
                    $formType,
                    $formInfo['data'],
                    [
                        'validation_groups' => $formInfo['validation_groups'],
                    ]
                );

                // If the form has errors, perform a validation, to restore the internal error context
                // A controller (as the NewsletterController) may use the parserContext to redisplay a
                // validated (not errored) form. In such cases, another validation may cause unexpected
                // results.
                if (true === $formInfo['hasError']) {
                    try {
                        $this->formValidator->validateForm($form, $formInfo['method']);
                    } catch (\Exception $ex) {
                        // Ignore the exception.
                    }

                    // Manually set the form fields error information, if validateForm() did not the job,
                    // which is the case when the user has been redirected.
                    foreach ($formInfo['field_errors'] as $fieldName => $errors) {
                        /** @var Form $field */
                        $field = $form->getForm()->get($fieldName);

                        if (null !== $field && \count($field->getErrors()) == 0) {
                            foreach ($errors as $errorData) {
                                $error = new FormError(
                                    $errorData['message'],
                                    $errorData['template'],
                                    $errorData['parameters'],
                                    $errorData['pluralization']
                                );

                                $field->addError($error);
                            }
                        }
                    }
                }

                $form->setError($formInfo['hasError']);

                // Check if error message is empty, as BaseForm::setErrorMessage() always set form error flag to true.
                if (!empty($formInfo['errorMessage'])) {
                    $form->setErrorMessage($formInfo['errorMessage']);
                }

                return $form;
            }
        }

        return null;
    }

    /**
     * Remove form from the saved form error information.
     *
     * @return $this
     */
    public function clearForm(BaseForm $form): self
    {
        $formErrorInformation = $this->getSession()->getFormErrorInformation();

        $formClass = $form::class.':'.$form->getType();

        if (isset($formErrorInformation[$formClass])) {
            unset($formErrorInformation[$formClass]);
            $this->getSession()->setFormErrorInformation($formErrorInformation);
        }

        return $this;
    }

    /**
     * Remove obsolete form error information.
     */
    protected function cleanOutdatedFormErrorInformation()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (
            !$request->hasSession(true)
             ||
            !$request->getSession()->isStarted()
        ) {
            return $this;
        }

        $formErrorInformation = $request->getSession()->getFormErrorInformation();

        if (!empty($formErrorInformation)) {
            $now = time();

            // Cleanup obsolete form information, and try to find the form data
            foreach ($formErrorInformation as $name => $formData) {
                if ($now - $formData['timestamp'] > self::FORM_ERROR_LIFETIME_SECONDS) {
                    unset($formErrorInformation[$name]);
                }
            }

            $this->getSession()->setFormErrorInformation($formErrorInformation);
        }

        return $this;
    }

    public function setGeneralError($error)
    {
        $this->set('general_error', $error);

        return $this;
    }

    // -- Internal table manipulation ------------------------------------------

    public function set($name, $value)
    {
        $this->store[$name] = $value;

        return $this;
    }

    public function remove($name)
    {
        unset($this->store[$name]);

        return $this;
    }

    public function get($name, $default = null)
    {
        return $this->store[$name] ?? $default;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->store);
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->requestStack->getCurrentRequest()->getSession();
    }
}
