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

namespace Thelia\Core\Security\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Model\Admin;

#[AsEventListener(event: CheckPassportEvent::class, priority: -64, dispatcher: 'security.event_dispatcher.adminLogin')]
final readonly class AdminApiTwoFactorListener
{
    public const CODE_FIELD = 'code';

    public function __construct(
        private AdminTwoFactorManager $twoFactorManager,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $admin = $event->getPassport()->getUser();

        if (!$admin instanceof Admin) {
            return;
        }

        if (!$this->twoFactorManager->isEnabledFor($admin)) {
            if ($this->twoFactorManager->isRequired()) {
                throw new BadCredentialsException('The second factor of this account must be enabled from the back office first.');
            }

            return;
        }

        $code = $this->submittedCode();

        if (null === $code || !$this->twoFactorManager->verify($admin, $code)->isAccepted()) {
            throw new BadCredentialsException('Invalid second factor code.');
        }
    }

    private function submittedCode(): ?string
    {
        $content = $this->requestStack->getCurrentRequest()?->getContent();

        if (!\is_string($content) || '' === $content) {
            return null;
        }

        try {
            $payload = json_decode($content, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $code = \is_array($payload) ? ($payload[self::CODE_FIELD] ?? null) : null;

        return \is_string($code) && '' !== $code ? $code : null;
    }
}
