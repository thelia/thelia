<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Form;

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\SessionCsrfProvider;
use Symfony\Component\Validator\Validation;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * Base form class for creating form objects
 *
 * Class BaseForm
 * @package Thelia\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
abstract class BaseForm
{
    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    protected $formBuilder;

    /**
     * @var \Symfony\Component\Form\Form
     */
    protected $form;

    protected $request;

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

    public function __construct(Request $request, $type= "form", $data = array(), $options = array())
    {
        $this->request = $request;

        $validator = Validation::createValidator();

        if (!isset($options["attr"]["name"])) {
            $options["attr"]["thelia_name"] = $this->getName();
        }

        $builder =  Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension());
        if (!isset($options["csrf_protection"]) || $options["csrf_protection"] !== false) {
            $builder->addExtension(
                new CsrfExtension(
                    new SessionCsrfProvider(
                        $request->getSession(),
                        isset($options["secret"]) ? $options["secret"] : ConfigQuery::read("form.secret", md5(__DIR__))
                    )
                )
            );
        }
        $this->formBuilder = $builder
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
            ->createNamedBuilder($this->getName(), $type, $data, $this->cleanOptions($options));
        ;

        $this->buildForm();

        // If not already set, define the success_url field
        if (! $this->formBuilder->has('success_url')) {
            $this->formBuilder->add("success_url", "text");
        }

        if (! $this->formBuilder->has('error_message')) {
            $this->formBuilder->add("error_message", "text");
        }

        $this->form = $this->formBuilder->getForm();
    }

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
     * Returns the absolute URL to redirect the user to if the form is successfully processed.
     *
     * @param string $default the default URL. If not given, the configured base URL is used.
     *
     * @return string an absolute URL
     */
    public function getSuccessUrl($default = null)
    {
        $successUrl = $this->form->get('success_url')->getData();

        if (empty($successUrl)) {

            if ($default === null) $default = ConfigQuery::read('base_url', '/');

            $successUrl = $default;
        }

        return URL::getInstance()->absoluteUrl($successUrl);
    }

    public function createView()
    {
        $this->view = $this->form->createView();

        return $this;
    }

    public function getView()
    {
        if ($this->view === null) throw new \LogicException("View was not created. Please call BaseForm::createView() first.");
        return $this->view;
    }

    // -- Error and errro message ----------------------------------------------

    /**
     * Set the error status of the form.
     *
     * @param boolean $has_error
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
     * @param unknown $message
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

    /**
     * @return string the name of you form. This name must be unique
     */
    abstract public function getName();
}
