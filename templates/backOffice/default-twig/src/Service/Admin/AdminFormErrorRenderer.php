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

use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Push a form validation error to the user through the session flash bag and attach a
 * matching FormError to the form so the Twig form theme highlights the offending fields.
 */
readonly class AdminFormErrorRenderer
{
    public function __construct(
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function setup(
        string $actionLabel,
        string $errorMessage,
        ?FormInterface $form = null,
        ?\Throwable $exception = null,
    ): void {
        $this->logger->error(
            $this->translator->trans(
                'Error during %action process: %error. Exception was %exc',
                [
                    '%action' => $actionLabel,
                    '%error' => $errorMessage,
                    '%exc' => $exception?->getMessage() ?? 'no exception',
                ],
            ),
        );

        $session = $this->requestStack->getMainRequest()?->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add('danger', $errorMessage);
        }

        if (null === $form) {
            return;
        }

        $form->addError(new \Symfony\Component\Form\FormError($errorMessage));
    }
}
