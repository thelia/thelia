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
use Thelia\Model\OrderStatus;
use Thelia\Model\QuoteQuery;

/**
 * Reads, with each quote of a list or an item, the order the quote became: the id and the reference
 * of the order that names it and that was not cancelled.
 *
 * Two subqueries of the quote query bring them back on the row of the quote, so a list costs the
 * same number of statements whatever its length. A status counts as cancelled through the code it
 * stands for, the way the model reads it.
 */
final readonly class QuoteOrderExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public const string ID_COLUMN = 'LiveOrderId';

    public const string REF_COLUMN = 'LiveOrderRef';

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

        foreach ([self::ID_COLUMN => 'id', self::REF_COLUMN => 'ref'] as $alias => $column) {
            $query->withColumn(\sprintf(
                "(SELECT `order`.`%s` FROM `order` INNER JOIN `order_status` ON `order_status`.`id` = `order`.`status_id` WHERE `order`.`quote_id` = `quote`.`id` AND CASE WHEN `order_status`.`protected_status` = 1 THEN `order_status`.`code` ELSE COALESCE(NULLIF(`order_status`.`equivalent_code`, ''), `order_status`.`code`) END <> '%s' ORDER BY `order`.`id` DESC LIMIT 1)",
                $column,
                OrderStatus::CODE_CANCELED,
            ), $alias);
        }
    }
}
