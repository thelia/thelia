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

namespace Thelia\Domain\Ai\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiActorResolverInterface;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

/**
 * Who a call is made for: the signed-in administrator, the signed-in customer, the visitor (by session, by address
 * without one), or the system when no request is under way (a command, a worker of Messenger).
 *
 * It never assumes a request exists. What identifies a visitor (the session identifier or the IP address) is
 * personal data: it leaves this class only as an HMAC with the secret of the application.
 *
 * @internal
 */
class AiActorResolver implements AiActorResolverInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SecurityContext $securityContext,
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function resolve(?AiAudience $audience = null): AiActor
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            return AiActor::system($this->process());
        }

        // An administrator who tries the chat of the shop is a customer or a visitor of it, never an administrator.
        $admin = AiAudience::Public === $audience || AiAudience::Customer === $audience ? null : $this->securityContext->getAdminUser();

        if ($admin instanceof Admin) {
            return AiActor::admin($admin->getId());
        }

        $customer = $this->securityContext->getCustomerUser();

        if ($customer instanceof Customer && !$customer->isGuest()) {
            return AiActor::customer($customer->getId());
        }

        $session = $request->hasSession() ? $request->getSession()->getId() : '';
        $visitor = '' !== $session ? 'session:'.$session : 'ip:'.($request->getClientIp() ?? 'unknown');

        return AiActor::visitor(hash_hmac('sha256', 'visitor:'.$visitor, $this->secret));
    }

    /**
     * The command that is running (`thelia:ai:rotate-keys`, `messenger:consume`), `cli` when there is none to name.
     */
    private function process(): string
    {
        $command = $_SERVER['argv'][1] ?? null;

        if (\is_string($command) && 1 === preg_match('/^[A-Za-z][A-Za-z0-9:_-]{0,63}$/', $command)) {
            return $command;
        }

        return \PHP_SAPI === 'cli' ? 'cli' : 'background';
    }
}
