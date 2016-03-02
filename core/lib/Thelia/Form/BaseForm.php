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

namespace Thelia\Form;

use Symfony\Component\Config\Definition\Builder\ValidationBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Validator\Validation;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * Base form class for creating form objects
 *
 * Class BaseForm
 * @package Thelia\Form
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseForm
{
    /**
     * @var \Symfony\Component\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * @var \Symfony\Component\Form\FormFactoryBuilderInterface
     */
    protected $formFactoryBuilder;

    /**
     * @var \Symfony\Component\Form\Form
     */
    protected $form;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var \Symfony\Component\Validator\ValidatorBuilderInterface
     */
    protected $validatorBuilder;

    /**
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    private $view = null;

    /**
     * true if the form has an error, false otherwise.
     * @var boolean
     */
    private $has_error = false;

    /**
     * The form error message.
     * @var string
     */
    private $error_message = '';

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string The form name
     */
    private $formUniqueIdentifier;

    /**
     * @param Request            $request
     * @param string             $type
     * @param array              $data
     * @param array              $options
     * @param ContainerInterface $container
     * @deprecated Thelia forms should not be instantiated directly. Please use BaseController::createForm() instead
     * @see BaseController::createForm()
     */
    public function __construct(
        Request $request,
        $type = "form",
        $data = array(),
        $options = array(),
        ContainerInterface $container = null
    ) {
        // Generate the form name from the complete class name
        $this->formUniqueIdentifier = strtolower(str_replace('\\', '_', get_class($this)));

        $this->request = $request;
        $this->type = $type;

        if (null !== $container) {
            $this->container = $container;
            $this->dispatcher = $container->get("event_dispatcher");

            $this->initFormWithContainer($type, $data, $options);
        } else {
            $this->initFormWithRequest($type, $data, $options);
        }

        if (!isset($options["csrf_protection"]) || $options["csrf_protection"] !== false) {
            $this->formFactoryBuilder
                ->addExtension(
                    new CsrfExtension(
                        new CsrfTokenManager(null, new SessionTokenStorage(
                            $this->getRequest()->getSession()
                        ))
                    )
                )
            ;
        }

        $this->formBuilder = $this->formFactoryBuilder
            ->addExtension(new ValidatorExtension($this->validatorBuilder->getValidator()))
            ->getFormFactory()
            ->createNamedBuilder($this->getName(), $type, $data, $this->cleanOptions($options))
        ;

        /**
         * Build the form
         */
        $name = $this->getName();

        $event = null;

        $dispatchEvents = $this->hasContainer() && $name !== null && $name !== '';

        // We need to wrap the dispatch with a condition for backward compatibility
        if ($dispatchEvents) {
            $event = new TheliaFormEvent($this);

            /**
             * If the form has the container, disptach the events
             */
            $this->dispatcher->dispatch(
                TheliaEvents::FORM_BEFORE_BUILD.".".$name,
                $event
            );
        }

        $this->buildForm();

        if ($dispatchEvents) {
            /**
             * If the form has the container, disptach the events
             */
            $this->dispatcher->dispatch(
                TheliaEvents::FORM_AFTER_BUILD.".".$name,
                $event
            );
        }

        // If not already set, define the success_url field
        // This field is not included in the standard form hidden fields
        // This field is not included in the hidden fields generated by form_hidden_fields Smarty function
        if (! $this->formBuilder->has('success_url')) {
            $this->formBuilder->add("success_url", "hidden");
        }

        // If not already set, define the error_url field
        // This field is not included in the standard form hidden fields
        // This field is not included in the hidden fields generated by form_hidden_fields Smarty function
        if (! $this->formBuilder->has('error_url')) {
            $this->formBuilder->add("error_url", "hidden");
        }

        // The "error_message" field defines the error message displayed if
        // the form could not be validated. If it is empty, a standard error message is displayed instead.
        // This field is not included in the hidden fields generated by form_hidden_fields Smarty function
        if (! $this->formBuilder->has('error_message')) {
            $this->formBuilder->add("error_message", "hidden");
        }

        $this->form = $this->formBuilder->getForm();
    }

    public function initFormWithContainer($type, $data, $options)
    {
        /** @var Translator translator */
        $this->translator = $this->container->get("thelia.translator");

        /** @var FormFactoryBuilderInterface formFactoryBuilder */
        $this->formFactoryBuilder = $this->container->get("thelia.form_factory_builder");

        /** @var ValidationBuilder validatorBuilder */
        $this->validatorBuilder = $this->container->get("thelia.forms.validator_builder");
    }

    protected function initFormWithRequest($type, $data, $options)
    {
        $this->validatorBuilder = Validation::createValidatorBuilder();

        $this->formFactoryBuilder =  Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
        ;

        $this->translator = Translator::getInstance();

        $this->validatorBuilder
            ->setTranslationDomain('validators')
            ->setTranslator($this->translator);
    }

    /**
     * @return \Symfony\Component\Form\FormBuilderInterface
     */
    public function getFormBuilder()
    {
        return $this->formBuilder;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Return true if the given field value is defined only in the HTML template, and its value is defined
     * in the template file, not the form builder.
     * Thus, it should not be included in the form hidden fields generated by form_hidden_fields
     * Smarty function, to prevent it from existing twice in the form.
     *
     * @param  FormView $fieldView
     * @return bool
     */
    public function isTemplateDefinedHiddenField(FormView $fieldView)
    {
        return $this->isTemplateDefinedHiddenFieldName($fieldView->vars['name']);
    }

    public function isTemplateDefinedHiddenFieldName($fieldName)
    {
        return $fieldName == 'success_url' || $fieldName == 'error_url' || $fieldName == 'error_message';
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    protected function cleanOptions($options)
    {
        unset($options["csrf_protection"]);

        return $options;
    }

    /**
     * Returns the absolute URL to redirect the user to if the form is not successfully processed,
     * using the 'error_url' form parameter value
     *
     * @param string $default the default URL. If not given, the configured base URL is used.
     * @return string an absolute URL
     */
    public function getErrorUrl($default = null)
    {
        return $this->getFormDefinedUrl('error_url', $default);
    }

    /**
     * @return bool true if an error URL is defined in the 'error_url' form parameter, false otherwise
     */
    public function hasErrorUrl()
    {
        return null !== $this->form->get('error_url')->getData();
    }

    /**
     * Returns the absolute URL to redirect the user to if the form is successfully processed.
     * using the 'success_url' form parameter value
     *
     * @param string $default the default URL. If not given, the configured base URL is used.
     * @return string an absolute URL
     */
    public function getSuccessUrl($default = null)
    {
        return $this->getFormDefinedUrl('success_url', $default);
    }

    /**
     * @return bool true if a success URL is defined in the form, false otherwise
     */
    public function hasSuccessUrl()
    {
        return null !== $this->form->get('success_url')->getData();
    }

    /**
     * Build an absolute URL using the value of a form parameter.
     *
     * @param string $parameterName the form parameter name
     * @param string $default a default value for the form parameter. If not defined, the configured base URL is used.
     * @return string an absolute URL
     */
    public function getFormDefinedUrl($parameterName, $default = null)
    {
        $formDefinedUrl = $this->form->get($parameterName)->getData();

        if (empty($formDefinedUrl)) {
            if ($default === null) {
                $default = ConfigQuery::read('base_url', '/');
            }

            $formDefinedUrl = $default;
        }

        return URL::getInstance()->absoluteUrl($formDefinedUrl);
    }

    public function createView()
    {
        $this->view = $this->form->createView();

        return $this;
    }

    /**
     * @return FormView
     * @throws \LogicException
     */
    public function getView()
    {
        if ($this->view === null) {
            throw new \LogicException("View was not created. Please call BaseForm::createView() first.");
        }

        return $this->view;
    }

    // -- Error and errro message ----------------------------------------------

    /**
     * Set the error status of the form.
     *
     * @param boolean $has_error
     * @return $this
     */
    public function setError($has_error = true)
    {
        $this->has_error = $has_error;

        return $this;
    }

    /**
     * Get the cuirrent error status of the form.
     *
     * @return boolean
     */
    public function hasError()
    {
        return $this->has_error;
    }

    /**
     * Set the error message related to global form error
     *
     * @param string $message
     * @return $this
     */
    public function setErrorMessage($message)
    {
        $this->setError(true);
        $this->error_message = $message;

        return $this;
    }

    /**
     * Get the form error message.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->error_message;
    }

    /**
     * @return \Symfony\Component\Form\Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @return bool
     */
    public function hasContainer()
    {
        return $this->container !== null;
    }

    /**
     * Override this method if you don't want to use the standard name, which is created from the full class name.
     *
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return $this->formUniqueIdentifier;
    }

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     *
     * @return null
     */
    abstract protected function buildForm();
}
