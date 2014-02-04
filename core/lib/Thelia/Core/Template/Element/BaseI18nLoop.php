<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Element;


use Thelia\Core\Template\Loop\Argument\Argument;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\Tools\ModelCriteriaTools;

/**
 *
 * Class BaseI18nLoop, imlplemented by loops providing internationalized data, such as title, description, etc.
 *
 * @package Thelia\Core\Template\Element
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

        $args[] = Argument::createIntTypeArgument('lang');

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
    protected function configureI18nProcessing(ModelCriteria $search, $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'), $foreignTable = null, $foreignKey = 'ID', $forceReturn = false)
    {
        /* manage translations */

        $this->locale = ModelCriteriaTools::getI18n(
            $this->getBackend_context(),
            $this->getLang(),
            $search,
            $this->request->getSession()->getLang()->getLocale(),
            $columns,
            $foreignTable,
            $foreignKey,
            $this->getForce_return()
        );
    }
}
