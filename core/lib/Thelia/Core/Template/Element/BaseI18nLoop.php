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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Loop\Argument\Argument;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\Tools\ModelCriteriaTools;

/**
 *
 * Class BaseI18nLoop, imlplemented by loops providing internationalized data, such as title, description, etc.
 *
 * @package Thelia\Core\Template\Element
 *
 * {@inheritdoc}
 * @method string getLang()
 */
abstract class BaseI18nLoop extends BaseLoop
{
    protected $locale;

    /**
     * Define common loop arguments
     *
     * @return Argument[]
     */
    protected function getDefaultArgs()
    {
        $args = parent::getDefaultArgs();

        $args[] = Argument::createAnyTypeArgument('lang');

        return $args;
    }

    /**
     * Setup ModelCriteria for proper i18n processing
     *
     * @param ModelCriteria $search       the Propel Criteria to configure
     * @param array         $columns      the i18n columns
     * @param string        $foreignTable the specified table (default  to criteria table)
     * @param string        $foreignKey   the foreign key in this table (default to criteria table)
     * @param bool          $forceReturn
     *
     * @return mixed the locale
     */
    protected function configureI18nProcessing(
        ModelCriteria $search,
        $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
        $foreignTable = null,
        $foreignKey = 'ID',
        $forceReturn = false
    ) {
        /* manage translations */

        $this->locale = ModelCriteriaTools::getI18n(
            $this->getBackendContext(),
            $this->getLang(),
            $search,
            $this->getCurrentRequest()->getSession()->getLang()->getLocale(),
            $columns,
            $foreignTable,
            $foreignKey,
            $this->getForceReturn()
        );
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
    public function addSearchInI18nColumn($search, $columnName, $searchCriteria, $searchTerm)
    {
        if (! $this->getBackendContext()) {
            $search->where(
                "CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID)
                        THEN `requested_locale_i18n`.`$columnName`
                        ELSE `default_locale_i18n`.`$columnName`
                        END " . $searchCriteria . " ?",
                $searchTerm,
                \PDO::PARAM_STR
            );
        } else {
            $search->where(
                "`requested_locale_i18n`.`$columnName` $searchCriteria ?",
                $searchTerm,
                \PDO::PARAM_STR
            );
        }
    }
}
