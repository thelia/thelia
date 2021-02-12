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

namespace Thelia\Core\Form;

use Symfony\Component\Form\Form;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class TheliaFormValidator.
 *
 * @author Benjamin Perche <benjamin@thelia.net>
 */
interface TheliaFormValidatorInterface
{
    /**
     * Validate a BaseForm.
     *
     * @param BaseForm $aBaseForm      the form
     * @param string   $expectedMethod the expected method, POST or GET, or null for any of them
     *
     * @throws FormValidationException is the form contains error, or the method is not the right one
     *
     * @return \Symfony\Component\Form\Form Form the symfony form object
     */
    public function validateForm(BaseForm $aBaseForm, $expectedMethod = null);

    /**
     * Get all errors that occurred in a form.
     *
     * @return string the error string
     */
    public function getErrorMessages(Form $form);
}
