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

use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\BaseForm;
use Symfony\Component\Form\Form;

/**
 * Class TheliaFormValidator
 * @package Thelia\Core
 * @author Benjamin Perche <benjamin@thelia.net>
 */
interface TheliaFormValidatorInterface
{
    /**
     * Validate a BaseForm
     *
     * @param  BaseForm                     $aBaseForm      the form
     * @param  string                       $expectedMethod the expected method, POST or GET, or null for any of them
     * @throws FormValidationException      is the form contains error, or the method is not the right one
     * @return \Symfony\Component\Form\Form Form the symfony form object
     */
    public function validateForm(BaseForm $aBaseForm, $expectedMethod = null);

    /**
     * Get all errors that occurred in a form
     *
     * @param  \Symfony\Component\Form\Form $form
     * @return string                       the error string
     */
    public function getErrorMessages(Form $form);
}
