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

namespace Thelia\Domain\Search\Provider;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryQuery;

final class CategoryDocumentProvider extends TranslatedCatalogueDocumentProvider
{
    public const string FAMILY = 'category';

    public function family(): string
    {
        return self::FAMILY;
    }

    protected function entityQuery(): ModelCriteria
    {
        return CategoryQuery::create();
    }

    protected function i18nQuery(): ModelCriteria
    {
        return CategoryI18nQuery::create();
    }
}
