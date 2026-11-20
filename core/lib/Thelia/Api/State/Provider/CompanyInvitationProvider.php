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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Domain\Company\Service\CompanyInvitationService;
use Thelia\Model\CompanyQuery;

/**
 * What an invitation says to the person it is sent to, who has no account yet: the company and the
 * address, and nothing else. Whatever is wrong with the token, it is the same 404.
 */
final readonly class CompanyInvitationProvider implements ProviderInterface
{
    public function __construct(private CompanyInvitationService $invitations)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $token = $uriVariables['token'] ?? null;
        $invitation = \is_string($token) ? $this->invitations->read($token) : null;
        $company = null === $invitation ? null : CompanyQuery::create()->findPk($invitation->companyId);

        if (null === $invitation || null === $company) {
            throw new NotFoundHttpException('No such invitation.');
        }

        return new JsonResponse([
            'companyName' => $company->getName(),
            'email' => $invitation->email,
        ]);
    }
}
