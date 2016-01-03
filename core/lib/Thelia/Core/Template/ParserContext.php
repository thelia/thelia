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

namespace Thelia\Core\Template;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Form\TheliaFormFactoryInterface;
use Thelia\Core\Form\TheliaFormValidatorInterface;
use Thelia\Core\HttpFoundation\Request;
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
    const FORM_ERROR_LIFETIME_SECONDS = 60;

    private $formStore = array();
    private $store = array();

    private $formFactory ;
    private $formValidator;

    /** @var  Request */
    private $request;

    public function __construct(
        Request $request,
        TheliaFormFactoryInterface $formFactory,
        TheliaFormValidatorInterface $formValidator
    ) {
        // Setup basic variables
        $this->set('THELIA_VERSION', Thelia::THELIA_VERSION);

        $this->request = $request;
        $this->formFactory = $formFactory;
        $this->formValidator = $formValidator;

        // Purge outdated error form contexts
        $this->cleanOutdatedFormErrorInformation();
    }

    /**
     * Set the current form
     *
     * @param BaseForm $form
     * @return $this
     */
    public function pushCurrentForm(BaseForm $form)
    {
        array_push($this->formStore, $form);

        return $this;
    }

    /**
     * Set the current form.
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
                "There is currently no defined form"
            );
        }

        return $form;
    }

    // -- Error form -----------------------------------------------------------

    /**
     * Remove all objects in data, because they are probably not serializable
     *
     * @param array $data
     * @return array
     */
    protected function cleanFormData(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->cleanFormData($value);
            } elseif (is_object($value)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Add a new form to the error form context
     *
     * @param BaseForm $form the errored form
     * @return $this
     */
    public function addForm(BaseForm $form)
    {
        $formErrorInformation = $this->request->getSession()->getFormErrorInformation();

        $this->set(get_class($form) . ":" . $form->getType(), $form);

        // Set form error information
        $formErrorInformation[get_class($form) . ":" . $form->getType()] = [
            'data'              => $this->cleanFormData($form->getForm()->getData()),
            'hasError'          => $form->hasError(),
            'errorMessage'      => $form->getErrorMessage(),
            'method'            => $this->request->getMethod(),
            'timestamp'         => time(),
            'validation_groups' => $form->getForm()->getConfig()->getOption('validation_groups')
        ];

        $this->request->getSession()->setFormErrorInformation($formErrorInformation);

        return $this;
    }

    /**
     * Check if the specified form has errors, and return an instance of this form if it's the case.
     *
     * @param string $formId the form ID, as defined in the container
     * @param string $formClass the form full qualified class name
     * @param string $formType the form type, something like 'form'
     * @return null|BaseForm null if no error information is available
     */
    public function getForm($formId, $formClass, $formType)
    {
        if (isset($this->store[$formClass . ":" . $formType]) && $this->store[$formClass . ":" . $formType] instanceof BaseForm) {
            return $this->store[$formClass . ":" . $formType];
        }

        $formErrorInformation = $this->request->getSession()->getFormErrorInformation();

        if (isset($formErrorInformation[$formClass.":".$formType])) {
            $formInfo = $formErrorInformation[$formClass.":".$formType];

            if (is_array($formInfo['data'])) {
                $form = $this->formFactory->createForm(
                    $formId,
                    $formType,
                    $formInfo['data'],
                    [
                        'validation_groups' => $formInfo['validation_groups']
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
                }

                $form->setError($formInfo['hasError']);

                // Check if error message is empty, as BaseForm::setErrorMessage() always set form error flag to true.
                if (! empty($formInfo['errorMessage'])) {
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
     * @param BaseForm $form
     * @return $this
     */
    public function clearForm(BaseForm $form)
    {
        $formErrorInformation = $this->request->getSession()->getFormErrorInformation();

        $formClass = get_class($form) . ':' . $form->getType();

        if (isset($formErrorInformation[$formClass])) {
            unset($formErrorInformation[$formClass]);
            $this->request->getSession()->setFormErrorInformation($formErrorInformation);
        }

        return $this;
    }

    /**
     * Remove obsolete form error information.
     */
    protected function cleanOutdatedFormErrorInformation()
    {
        $formErrorInformation = $this->request->getSession()->getFormErrorInformation();

        if (! empty($formErrorInformation)) {
            $now = time();

            // Cleanup obsolete form information, and try to find the form data
            foreach ($formErrorInformation as $name => $formData) {
                if ($now - $formData['timestamp'] > self::FORM_ERROR_LIFETIME_SECONDS) {
                    unset($formErrorInformation[$name]);
                }
            }

            $this->request->getSession()->setFormErrorInformation($formErrorInformation);
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
        return isset($this->store[$name]) ? $this->store[$name] : $default;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->store);
    }
}
