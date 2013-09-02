<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\LangQuery;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ConfigQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Config loop, to access configuration variables
 *
 * - id is the config id
 * - name is the config name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of config IDs that will be excluded from output
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Config extends BaseI18nLoop
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createAnyTypeArgument('variable'),
            Argument::createBooleanOrBothTypeArgument('hidden'),
            Argument::createBooleanOrBothTypeArgument('secured')
        );
     }

    /**
     * @param $pagination (ignored)
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $id      = $this->getId();
        $name    = $this->getVariable();
        $secured = $this->getSecured();

        $search = ConfigQuery::create();

        $locale = $this->configureI18nProcessing($search);

        if (! is_null($id))
            $search->filterById($id);

        if (! is_null($name))
            $search->filterByName($name);

        if (! is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if ($this->getHidden() != BooleanOrBothType::ANY)
            $search->filterByHidden($this->getHidden() ? 1 : 0);

        if (! is_null($secured) && $secured != BooleanOrBothType::ANY)
            $search->filterBySecured($secured ? 1 : 0);

        $search->orderByName(Criteria::ASC);

        $results = $this->search($search, $pagination);

        $loopResult = new LoopResult();

        foreach ($results as $result) {

            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set("ID"           , $result->getId())
                ->set("NAME"         , $result->getName())
                ->set("VALUE"        , $result->getValue())
                ->set("IS_TRANSLATED", $result->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$locale)
                ->set("TITLE"        , $result->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO"        , $result->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION"  , $result->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM" , $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("HIDDEN"       , $result->getHidden())
                ->set("SECURED"       , $result->getSecured())
                ->set("CREATE_DATE"  , $result->getCreatedAt())
                ->set("UPDATE_DATE"  , $result->getUpdatedAt())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
