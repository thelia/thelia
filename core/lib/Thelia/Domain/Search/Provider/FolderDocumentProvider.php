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
use Thelia\Model\FolderI18nQuery;
use Thelia\Model\FolderQuery;

final class FolderDocumentProvider extends TranslatedCatalogueDocumentProvider
{
    public const string FAMILY = 'folder';

    public function family(): string
    {
        return self::FAMILY;
    }

    protected function entityQuery(): ModelCriteria
    {
        return FolderQuery::create();
    }

    protected function i18nQuery(): ModelCriteria
    {
        return FolderI18nQuery::create();
    }
}
