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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveQuery\ModelCriteria;

trait StandardI18nFieldsSearchTrait
{
    protected static $standardI18nSearchFields = [
        'title',
        'chapo',
        'description',
        'postscriptum',
    ];

    protected function getStandardI18nSearchFields()
    {
        return self::$standardI18nSearchFields;
    }

    /**
     * @param string[] $searchIn
     */
    protected function addStandardI18nSearch(ModelCriteria $search, string $searchTerm, string $searchCriteria, array $searchIn = ['title', 'chapo', 'description', 'postscriptum']): void
    {
        $firstSearch = true;
        foreach (self::$standardI18nSearchFields as $searchInElement) {
            if (!\in_array($searchInElement, $searchIn, true)) {
                continue;
            }

            if (!$firstSearch) {
                $search->_or();
            }

            $this->addSearchInI18nColumn($search, strtoupper($searchInElement), $searchCriteria, $searchTerm);
            $firstSearch = false;
        }
    }
}
