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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\FrontOperation;
use Thelia\Api\Resource\Quote;
use Thelia\Api\Security\AccountCaller;
use Thelia\Api\Service\Quote\QuoteOwnershipScope;
use Thelia\Model\QuoteQuery;

/**
 * Scopes the quotes of the account endpoints to what the caller may reach.
 *
 * A quote is reached by a sequential number, and the generic customer filter of the account
 * endpoints would hide the quotes of a company from the buyers who may read them: this extension
 * replaces it on this resource, with the rule of {@see QuoteOwnershipScope}. The collection and the
 * item read are bounded by the same rule, and what is out of reach reads as "not found".
 *
 * Who the caller is comes from {@see AccountCaller}: the token when there is one, the session
 * customer for a page of the theme reading in-process, nobody for a guest.
 */
final readonly class QuoteOwnershipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private const string ACCOUNT_URI_PREFIX = '/front/account/';

    public function __construct(
        private AccountCaller $caller,
        private QuoteOwnershipScope $scope,
    ) {
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scope($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->scope($query, $resourceClass, $operation);
    }

    /**
     * Whether this extension, and not the generic customer filter, bounds the resource.
     */
    public static function handles(string $resourceClass, ?Operation $operation): bool
    {
        return Quote::class === $resourceClass
            && FrontOperation::isFront($operation)
            && $operation instanceof HttpOperation
            && str_starts_with((string) $operation->getUriTemplate(), self::ACCOUNT_URI_PREFIX);
    }

    private function scope(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (!self::handles($resourceClass, $operation) || !$query instanceof QuoteQuery) {
            return;
        }

        $this->scope->apply($query, $this->caller->customer());
    }
}
