<?php

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

/**
 *
 */
trait StandardI18nFieldsSearchTrait
{
    protected static $standardI18nSearchFields = [
        "title",
        "chapo",
        "description",
        "postscriptum"
    ];

    protected function getStandardI18nSearchFields()
    {
        return self::$standardI18nSearchFields;
    }

     /**
     * @param ModelCriteria $search
     * @param $searchTerm
     * @param $searchCriteria
     * @param string[] $searchIn
     */
    protected function addStandardI18nSearch(&$search, $searchTerm, $searchCriteria, $searchIn = [ "title", "chapo", "description", "postscriptum" ])
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
