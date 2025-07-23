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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Model\Base\ChoiceFilterOtherQuery as BaseChoiceFilterOtherQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'choice_filter_other' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ChoiceFilterOtherQuery extends BaseChoiceFilterOtherQuery
{
    public static function findOther($locales = ['en_US']): array|ObjectCollection
    {
        $otherQuery = self::create();

        $otherQuery->useChoiceFilterOtherI18nQuery(null, Criteria::LEFT_JOIN)
            ->endUse();

        $locales = array_map(static fn ($value): string => '"'.$value.'"', $locales);

        $otherQuery->addJoinCondition('ChoiceFilterOtherI18n', 'ChoiceFilterOtherI18n.locale IN ('.implode(',', $locales).')');

        $otherQuery->withColumn('ChoiceFilterOtherI18n.title', 'Title');

        $otherQuery->groupBy('id');

        return $otherQuery->find();
    }
}
