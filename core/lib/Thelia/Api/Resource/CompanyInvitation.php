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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody as OpenApiRequestBody;
use Thelia\Api\Security\CompanyVoter;
use Thelia\Api\State\Processor\CompanyInvitationAcceptProcessor;
use Thelia\Api\State\Processor\CompanyInvitationCreateProcessor;
use Thelia\Api\State\Provider\CompanyInvitationProvider;
use Thelia\Domain\Company\CompanyPermission;

/**
 * The invitation to join a company, a signed token that names the e-mail address it was sent to.
 *
 * Three operations, none of which keeps anything: a buyer who manages the buyers sends one (the
 * token goes in the e-mail and nowhere else, not in the answer), the person invited reads the
 * little it says before choosing a password, and an account signed in with the invited address
 * accepts it.
 *
 * The reading is public, since the person has no account yet, and answers the name of the company
 * and the address and nothing more; whatever is wrong with the token, altered, expired, used,
 * is the same 404. The acceptance is an account endpoint: a guest never joins a company, and an
 * address that is not the one invited is refused.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/account/company/invitations',
            status: 201,
            openapi: new OpenApiOperation(
                summary: 'Invite someone to join the company',
                description: 'Mails a link to the address, valid seven days. The role goes no further than the permissions of the caller and the limits are no higher than theirs. Answers 429 after too many invitations, 409 when the address already belongs to a buyer.',
                requestBody: new OpenApiRequestBody(
                    content: new \ArrayObject(['application/json' => ['schema' => self::INVITATION_SCHEMA]]),
                ),
            ),
            security: 'is_granted("'.CompanyVoter::PERMISSION.'", "'.CompanyPermission::BUYER_MANAGE.'")',
            input: false,
            read: false,
            deserialize: false,
            processor: CompanyInvitationCreateProcessor::class,
        ),
        new Post(
            uriTemplate: '/front/account/company/invitations/accept',
            status: 200,
            openapi: new OpenApiOperation(
                summary: 'Accept an invitation',
                description: 'Makes the signed-in account a buyer of the company the invitation names. The account must hold the address the invitation was sent to.',
                requestBody: new OpenApiRequestBody(
                    content: new \ArrayObject(['application/json' => ['schema' => self::ACCEPT_SCHEMA]]),
                ),
            ),
            security: 'is_granted("ROLE_CUSTOMER")',
            input: false,
            read: false,
            deserialize: false,
            processor: CompanyInvitationAcceptProcessor::class,
        ),
        new Get(
            uriTemplate: '/front/company-invitations/{token}',
            uriVariables: ['token'],
            openapi: new OpenApiOperation(
                summary: 'Read an invitation',
                description: 'The name of the company and the address invited, for the page where the person chooses a password. Any token that does not stand is a 404.',
            ),
            // Open on purpose, and stated rather than left out: the person invited has no account to
            // sign in with. The signature is the whole of the check, and the answer is two fields.
            security: 'true',
            read: true,
            provider: CompanyInvitationProvider::class,
        ),
    ],
)]
final class CompanyInvitation
{
    private const INVITATION_SCHEMA = [
        'type' => 'object',
        'required' => ['email', 'roleCode'],
        'properties' => [
            'email' => ['type' => 'string', 'format' => 'email'],
            'roleCode' => ['type' => 'string', 'description' => 'A role every company shares or one of the company.'],
            'orderLimit' => ['type' => 'string', 'nullable' => true, 'description' => 'A decimal string in the default currency.'],
            'periodLimit' => ['type' => 'string', 'nullable' => true, 'description' => 'A decimal string in the default currency.'],
            'period' => ['type' => 'string', 'nullable' => true, 'enum' => ['week', 'month', 'year']],
        ],
    ];

    private const ACCEPT_SCHEMA = [
        'type' => 'object',
        'required' => ['token'],
        'properties' => [
            'token' => ['type' => 'string'],
        ],
    ];

    #[ApiProperty(identifier: true)]
    public string $token = '';
}
