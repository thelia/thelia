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

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\MessageQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Message loop, to access messageuration variables
 *
 * - id is the message id
 * - name is the message name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of message IDs that will be excluded from output
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Message extends BaseI18nLoop
{
    public $timestampable = true;

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

        $search = MessageQuery::create();

        $locale = $this->configureI18nProcessing($search, array(
                'TITLE',
                'SUBJECT',
                'TEXT_MESSAGE',
                'HTML_MESSAGE'
            )
        );

        if (! is_null($id))
            $search->filterById($id);

        if (! is_null($name))
            $search->filterByName($name);

        if (! is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if (! is_null($secured) && $secured != BooleanOrBothType::ANY)
            $search->filterBySecured($secured ? 1 : 0);

        $search->orderByName(Criteria::ASC);

        $results = $this->search($search, $pagination);

        $loopResult = new LoopResult($results);

        foreach ($results as $result) {

            $loopResultRow = new LoopResultRow($loopResult, $result, $this->versionable, $this->timestampable, $this->countable);

            $loopResultRow
                ->set("ID"           , $result->getId())
                ->set("NAME"         , $result->getName())
                ->set("IS_TRANSLATED", $result->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE"       , $locale)
                ->set("TITLE"        , $result->getVirtualColumn('i18n_TITLE'))
                ->set("SUBJECT"      , $result->getVirtualColumn('i18n_SUBJECT'))
                ->set("TEXT_MESSAGE" , $result->getVirtualColumn('i18n_TEXT_MESSAGE'))
                ->set("HTML_MESSAGE" , $result->getVirtualColumn('i18n_HTML_MESSAGE'))
                ->set("SECURED"      , $result->getSecured())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
