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

use Thelia\Core\Form\TheliaFormFactoryInterface;
use Thelia\Core\Form\TheliaFormValidatorInterface;
use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;
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
     * @param BaseForm $form the errored form
     * @return $this
     */
    public function addForm(BaseForm $form)
    {
        $this->request->getSession()->addFormErrorInformation(
            get_class($form),
            [
                'data' => $form->getForm()->getData(),
                'hasError' => $form->hasError(),
                'errorMessage' => $form->getErrorMessage()
            ]
        );

        return $this;
    }

    public function getForm($formId, $formClass, $formType)
    {
        $formInfo = $this->request->getSession()->getFormErrorInformation($formClass);

        if ($formInfo !== null) {
            if (is_array($formInfo['data'])) {
                $form = $this->formFactory->createForm($formId, $formType, $formInfo['data']);

                // Perform validation to restore error context
                try {
                    $this->formValidator->validateForm($form);
                } catch (\Exception $ex) {
                    // Ignore the exception.
                }

                $form->setError($formInfo['hasError']);
                $form->setErrorMessage($formInfo['errorMessage']);

                return $form;
            }
        }

        return null;
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
