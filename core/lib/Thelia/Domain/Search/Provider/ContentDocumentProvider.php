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
use Thelia\Domain\CMS\Publication\ContentFolderPublicationRule;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Map\ContentTableMap;

final class ContentDocumentProvider extends TranslatedCatalogueDocumentProvider
{
    public const string FAMILY = 'content';

    public function __construct(
        private readonly ?ContentFolderPublicationRule $folderPublicationRule = null,
    ) {
    }

    public function family(): string
    {
        return self::FAMILY;
    }

    protected function entityQuery(): ModelCriteria
    {
        return ContentQuery::create();
    }

    protected function i18nQuery(): ModelCriteria
    {
        return ContentI18nQuery::create();
    }

    protected function narrowToTheVisitor(ModelCriteria $query): void
    {
        if (null !== $this->folderPublicationRule) {
            $query->where($this->folderPublicationRule->outsideAnUnreleasedFolderClause(ContentTableMap::COL_ID));
        }
    }
}
