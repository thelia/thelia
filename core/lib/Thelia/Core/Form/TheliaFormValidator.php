<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Thelia\Core\Form;

use Symfony\Component\Form\Form;
use Symfony\Component\Translation\TranslatorInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\FirewallForm;

/**
 * Class TheliaFormValidator
 * @package Thelia\Core
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class TheliaFormValidator implements TheliaFormValidatorInterface
{
    /** @var TranslatorInterface  */
    protected $translator;

    /** @var string */
    protected $environment;

    public function __construct(TranslatorInterface $translator, $environment)
    {
        $this->translator = $translator;
        $this->environment = $environment;
    }

    /**
     * Validate a BaseForm
     *
     * @param  BaseForm                     $aBaseForm      the form
     * @param  string                       $expectedMethod the expected method, POST or GET, or null for any of them
     * @throws FormValidationException      is the form contains error, or the method is not the right one
     * @return \Symfony\Component\Form\Form Form the symfony form object
     */
    public function validateForm(BaseForm $aBaseForm, $expectedMethod = null)
    {
        $form = $aBaseForm->getForm();

        if ($expectedMethod == null || $aBaseForm->getRequest()->isMethod($expectedMethod)) {
            $form->handleRequest($aBaseForm->getRequest());

            if ($form->isValid()) {
                if ($aBaseForm instanceof FirewallForm && !$aBaseForm->isFirewallOk($this->environment)) {
                    throw new FormValidationException(
                      $this->translator->trans("You've submitted this form too many times. ")
                      .$this->translator->trans("Further submissions will be ignored during %time",
                            [
                                "%time" => $aBaseForm->getWaitingTime(),
                            ]
                        )
                    );
                }

                return $form;
            } else {
                $errorMessage = null;
                if ($form->get("error_message")->getData() != null) {
                    $errorMessage = $form->get("error_message")->getData();
                } else {
                    $errorMessage = sprintf(
                        $this->translator->trans(
                            "Missing or invalid data: %s"
                        ),
                        $this->getErrorMessages($form)
                    );
                }
                $aBaseForm->setError(true);
                throw new FormValidationException($errorMessage);
            }
        } else {
            throw new FormValidationException(
                sprintf(
                    $this->translator->trans(
                        "Wrong form method, %s expected."
                    ),
                    $expectedMethod
                )
            );
        }
    }

    /**
     * Get all errors that occurred in a form
     *
     * @param  \Symfony\Component\Form\Form $form
     * @return string                       the error string
     */
    public function getErrorMessages(Form $form)
    {
        $errors = '';

        foreach ($form->getErrors() as $key => $error) {
            $errors .= $error->getMessage().', ';
        }

        /** @var Form $child */
        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                $fieldName = $child->getConfig()->getOption('label', null);

                if (empty($fieldName)) {
                    $fieldName = $child->getName();
                }

                $errors .= sprintf("[%s] %s, ", $fieldName, $this->getErrorMessages($child));
            }
        }

        return rtrim($errors, ', ');
    }
}
