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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\ModuleQuery;

use Thelia\Module\BaseModule;
use Thelia\Type;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;

/**
 *
 * Template loop, to get available back-office or front-office templates.
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Template extends BaseLoop implements ArraySearchLoopInterface
{
   /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            new Argument(
                'template-type',
                new Type\TypeCollection(
                    new Type\EnumType(array(
                        'front-office',
                        'back-office',
                        'pdf',
                        'email'
                    ))
                )
            )
        );
    }

    public function buildArray() {
        $type = $this->getArg('template-type')->getValue();

        if ($type == 'front-office')
            $templateType = TemplateDefinition::FRONT_OFFICE;
        else if ($type == 'back-office')
            $templateType = TemplateDefinition::BACK_OFFICE;
        else if ($type == 'pdf')
            $templateType = TemplateDefinition::PDF;
        else if ($type == 'email')
            $templateType = TemplateDefinition::EMAIL;

        return TemplateHelper::getInstance()->getList($templateType);
    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $template) {

            $loopResultRow = new LoopResultRow($template);

            $loopResultRow
                ->set("NAME"          , $template->getName())
                ->set("RELATIVE_PATH" , $template->getPath())
                ->set("ABSOLUTE_PATH" , $template->getAbsolutePath())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
