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

namespace Thelia\Api\Service\API;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Thelia\Api\Security\CompanyVoter;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Customer;

/**
 * Backs the `is_granted()` expression function when a resource security
 * expression is evaluated in-process (Twig `resources()`), outside any
 * Symfony firewall. The Symfony AuthorizationChecker only sees the token
 * of the current firewall, which is anonymous on front pages, so ROLE_*
 * checks are resolved against the Thelia session user instead. Anything
 * else falls back to the decorated Symfony checker, except what concerns a customer and their
 * company (see {@see CompanyVoter}): the voter is asked about the session customer directly, since
 * the token of the firewall is anonymous here and would refuse every order and address of the account.
 */
final readonly class SessionUserAuthorizationChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private mixed $user,
        private ?AuthorizationCheckerInterface $decorated,
        private ?CompanyVoter $companyVoter = null,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if (\is_string($attribute) && str_starts_with($attribute, 'ROLE_')) {
            return $this->user instanceof UserInterface
                && \in_array($attribute, $this->user->getRoles(), true);
        }

        if (\is_string($attribute) && null !== $this->companyVoter && $this->user instanceof Customer && \in_array($attribute, CompanyVoter::ATTRIBUTES, true)) {
            $token = new UsernamePasswordToken($this->user, 'front', $this->user->getRoles());

            return VoterInterface::ACCESS_GRANTED === $this->companyVoter->vote($token, $subject, [$attribute]);
        }

        return $this->decorated?->isGranted($attribute, $subject, $accessDecision) ?? false;
    }
}
