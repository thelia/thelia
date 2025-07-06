<?php

declare(strict_types=1);

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
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\FirewallForm;

/**
 * Class TheliaFormValidator.
 *
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class TheliaFormValidator
{
    public function __construct(protected TranslatorInterface $translator, protected $environment)
    {
    }

    /**
     * Validate a BaseForm.
     *
     * @param BaseForm $aBaseForm      the form
     * @param string   $expectedMethod the expected method, POST or GET, or null for any of them
     *
     * @return Form Form the symfony form object
     *
     * @throws FormValidationException is the form contains error, or the method is not the right one
     */
    public function validateForm(BaseForm $aBaseForm, ?string $expectedMethod = null): Form
    {
        $form = $aBaseForm->getForm();

        if (null === $expectedMethod || $aBaseForm->getRequest()->isMethod($expectedMethod)) {
            $form->handleRequest($aBaseForm->getRequest());

            if ($form->isValid()) {
                if ($aBaseForm instanceof FirewallForm && !$aBaseForm->isFirewallOk($this->environment)) {
                    throw new FormValidationException($this->translator->trans("You've submitted this form too many times. ") . $this->translator->trans('Further submissions will be ignored during %time', ['%time' => $aBaseForm->getWaitingTime()]));
                }

                return $form;
            }

            $errorMessage = null;

            if (null !== $form->get('error_message')->getData()) {
                $errorMessage = $form->get('error_message')->getData();
            } else {
                $errorMessage = \sprintf(
                    $this->translator->trans(
                        'Missing or invalid data: %s',
                    ),
                    $this->getErrorMessages($form),
                );
            }

            $aBaseForm->setError(true);

            throw new FormValidationException($errorMessage);
        }

        throw new FormValidationException(\sprintf($this->translator->trans('Wrong form method, %s expected.'), $expectedMethod));
    }

    /**
     * Get all errors that occurred in a form.
     *
     * @return string the error string
     */
    public function getErrorMessages(Form $form): string
    {
        $errors = '';

        foreach ($form->getErrors() as $error) {
            $errors .= $error->getMessage() . ', ';
        }

        /** @var Form $child */
        foreach ($form->all() as $child) {
            if ($child->isSubmitted() && !$child->isValid()) {
                $fieldName = $child->getConfig()->getOption('label', null);

                if (empty($fieldName)) {
                    $fieldName = $child->getName();
                }

                $errors .= \sprintf('[%s] %s, ', $fieldName, $this->getErrorMessages($child));
            }
        }

        return rtrim($errors, ', ');
    }
}
