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
use Thelia\Api\Resource\QuoteItem;
use Thelia\Api\Resource\QuoteRevision;
use Thelia\Api\Service\Quote\AdminQuoteLookup;

/**
 * Serves the lines of one revision, or one of them, of the quote named in the uri, the draft of the shop included,
 * with the counter-proposals the buyer wrote on them. The revision is read with its lines in three
 * statements however many lines there are.
 *
 * @implements ProviderInterface<QuoteItem>
 */
final readonly class QuoteItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminQuoteLookup $quotes,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<QuoteItem>|QuoteItem
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|QuoteItem
    {
        $quote = $this->quotes->fromUriVariables($uriVariables);
        $number = filter_var($uriVariables['number'] ?? null, \FILTER_VALIDATE_INT);
        $revision = false === $number ? null : (QuoteRevision::readAllOf($quote, true, $number)[0] ?? null);

        $items = ($revision ?? throw new NotFoundHttpException('No such revision.'))->items;

        if ($operation instanceof CollectionOperationInterface) {
            return $items;
        }

        $itemId = filter_var($uriVariables['itemId'] ?? null, \FILTER_VALIDATE_INT);

        foreach ($items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        throw new NotFoundHttpException('No such line on this revision.');
    }
}
