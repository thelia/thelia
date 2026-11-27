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

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\Quote;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Model\QuoteQuery;

/**
 * Reads, with each quote of a list or an item, the total with taxes of its latest revision sent.
 *
 * The total is frozen on the revision when it is sent: a subquery of the quote query brings it back
 * on the row of the quote, so a list costs the same number of statements whatever its length, and
 * a draft is never counted.
 */
final readonly class QuoteLatestTotalExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public const string VIRTUAL_COLUMN = 'LatestSentTotalTaxed';

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->select($query, $resourceClass);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->select($query, $resourceClass);
    }

    private function select(ModelCriteria $query, string $resourceClass): void
    {
        if (Quote::class !== $resourceClass || !$query instanceof QuoteQuery) {
            return;
        }

        $query->withColumn(\sprintf(
            "(SELECT `quote_revision`.`total_taxed` FROM `quote_revision` WHERE `quote_revision`.`quote_id` = `quote`.`id` AND `quote_revision`.`state` = '%s' ORDER BY `quote_revision`.`number` DESC LIMIT 1)",
            QuoteRevisionState::Sent->value,
        ), self::VIRTUAL_COLUMN);
    }
}
