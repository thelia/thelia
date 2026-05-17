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

namespace BackOfficeDefaultTwigBundle\Service\Admin;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Form\Exception\FormValidationException;

/**
 * Handle a submitted Symfony form against the current request and surface the
 * first error as a {@see FormValidationException} suitable for caller-side handling.
 */
readonly class AdminFormValidator
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @throws FormValidationException when the form is not submitted, the HTTP method does not match,
     *                                 or at least one constraint violation is present.
     */
    public function validate(FormInterface $form, string $expectedMethod = 'POST'): FormInterface
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            throw new FormValidationException('No HTTP request in stack — form cannot be validated.');
        }

        if (strtoupper($request->getMethod()) !== strtoupper($expectedMethod)) {
            throw new FormValidationException(
                \sprintf('Invalid HTTP method [%s], expected [%s].', $request->getMethod(), $expectedMethod),
            );
        }

        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new FormValidationException(\sprintf('Form [%s] was not submitted.', $form->getName()));
        }

        if ($form->isValid()) {
            return $form;
        }

        $errors = $form->getErrors(true, true);
        $firstError = $errors->current();

        throw new FormValidationException(
            $firstError ? (string) $firstError->getMessage() : 'Form contains validation errors.',
        );
    }
}
