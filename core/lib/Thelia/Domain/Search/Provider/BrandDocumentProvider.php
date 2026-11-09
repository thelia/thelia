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
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\BrandQuery;

final class BrandDocumentProvider extends TranslatedCatalogueDocumentProvider
{
    public const string FAMILY = 'brand';

    public function family(): string
    {
        return self::FAMILY;
    }

    protected function entityQuery(): ModelCriteria
    {
        return BrandQuery::create();
    }

    protected function i18nQuery(): ModelCriteria
    {
        return BrandI18nQuery::create();
    }
}
