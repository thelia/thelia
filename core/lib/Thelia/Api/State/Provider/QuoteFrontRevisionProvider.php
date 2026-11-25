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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Resource\QuoteRevision;
use Thelia\Api\Service\Quote\QuoteReach;

/**
 * Serves the revisions that were sent of a quote the caller may reach, by the rule that bounds the
 * quote itself: a quote out of reach, a draft of the shop and a number that was never written all
 * answer "not found".
 *
 * The quote is the scope, never a filter the caller may drop. The revisions are read in a number of
 * statements that does not depend on how many there are; the list leaves the lines out and one
 * revision brings them, in one statement.
 *
 * @implements ProviderInterface<QuoteRevision>
 */
final readonly class QuoteFrontRevisionProvider implements ProviderInterface
{
    public function __construct(
        private QuoteReach $reach,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<QuoteRevision>|QuoteRevision
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|QuoteRevision
    {
        $quote = $this->reach->readable($this->reach->customer(), $uriVariables);

        if ($operation instanceof CollectionOperationInterface) {
            return QuoteRevision::readAllOf($quote, false, null, false);
        }

        $number = filter_var($uriVariables['number'] ?? null, \FILTER_VALIDATE_INT);

        return (false === $number ? null : (QuoteRevision::readAllOf($quote, false, $number)[0] ?? null))
            ?? throw new NotFoundHttpException('No such revision.');
    }
}
