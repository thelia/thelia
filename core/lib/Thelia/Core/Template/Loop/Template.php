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

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Type;

/**
 *
 * Template loop, to get available back-office or front-office templates.
 *
 * @package Thelia\Core\Template\Loop
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
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

    public function buildArray()
    {
        $type = $this->getArg('template-type')->getValue();

        if ($type == 'front-office') {
            $templateType = TemplateDefinition::FRONT_OFFICE;
        } elseif ($type == 'back-office') {
            $templateType = TemplateDefinition::BACK_OFFICE;
        } elseif ($type == 'pdf') {
            $templateType = TemplateDefinition::PDF;
        } elseif ($type == 'email') {
            $templateType = TemplateDefinition::EMAIL;
        }

        return $this->container->get('thelia.template_helper')->getList($templateType);
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var TemplateDefinition $template */
        foreach ($loopResult->getResultDataCollection() as $template) {
            $loopResultRow = new LoopResultRow($template);

            $loopResultRow
                ->set("NAME", $template->getName())
                ->set("RELATIVE_PATH", $template->getPath())
                ->set("ABSOLUTE_PATH", $template->getAbsolutePath())
            ;
            $this->addOutputFields($loopResultRow, $template);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
