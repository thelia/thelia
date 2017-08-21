<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

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
     */
    protected function addStandardI18nSearch(&$search, $searchTerm, $searchCriteria)
    {
        foreach (self::$standardI18nSearchFields as $index => $searchInElement) {
            if ($index > 0) {
                $search->_or();
            }

            $this->addSearchInI18nColumn($search, strtoupper($searchInElement), $searchCriteria, $searchTerm);
        }
    }

    /**
     * Add the search clause for an I18N column, taking care of the back/front context, as default_locale_i18n is
     * not defined in the backEnd I18N context.
     *
     * @param ModelCriteria $search
     * @param string $columnName the column to search into, such as TITLE
     * @param string $searchCriteria the search criteria, such as Criterial::LIKE, Criteria::EQUAL, etc.
     * @param string $searchTerm the searched term
     */
    public abstract function addSearchInI18nColumn($search, $columnName, $searchCriteria, $searchTerm);
}
