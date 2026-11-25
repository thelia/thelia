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
use Thelia\Api\Service\Quote\AdminQuoteLookup;

/**
 * Serves the revisions of the quote named in the uri, the draft of the shop included: the
 * administration reads what the quote itself reads there.
 *
 * The quote is the scope, never a filter the caller may drop. The revisions are read in a number of
 * statements that does not depend on how many there are; the list leaves the lines out and one
 * revision brings them.
 *
 * @implements ProviderInterface<QuoteRevision>
 */
final readonly class QuoteRevisionProvider implements ProviderInterface
{
    public function __construct(
        private AdminQuoteLookup $quotes,
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
        $quote = $this->quotes->fromUriVariables($uriVariables);

        if ($operation instanceof CollectionOperationInterface) {
            return QuoteRevision::readAllOf($quote, true, null, false);
        }

        $number = filter_var($uriVariables['number'] ?? null, \FILTER_VALIDATE_INT);

        return (false === $number ? null : (QuoteRevision::readAllOf($quote, true, $number)[0] ?? null))
            ?? throw new NotFoundHttpException('No such revision.');
    }
}
