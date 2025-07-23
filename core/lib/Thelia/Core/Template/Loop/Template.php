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

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Template loop, to get available back-office or front-office templates.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Template extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            new Argument(
                'template-type',
                new TypeCollection(
                    new EnumType([
                        'front-office',
                        'front',
                        'back-office',
                        'admin',
                        'pdf',
                        'email',
                        'mail',
                    ]),
                ),
            ),
        );
    }

    public function buildArray(): array
    {
        $type = $this->getArg('template-type')->getValue();

        $templateType = TemplateDefinition::FRONT_OFFICE;

        if ('front-office' === $type || 'front' === $type) {
            $templateType = TemplateDefinition::FRONT_OFFICE;
        } elseif ('back-office' === $type || 'admin' === $type) {
            $templateType = TemplateDefinition::BACK_OFFICE;
        } elseif ('pdf' === $type) {
            $templateType = TemplateDefinition::PDF;
        } elseif ('email' === $type || 'mail' === $type) {
            $templateType = TemplateDefinition::EMAIL;
        }

        return $this->container->get('thelia.template_helper')->getList($templateType);
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var TemplateDefinition $template */
        foreach ($loopResult->getResultDataCollection() as $template) {
            $loopResultRow = new LoopResultRow($template);

            $loopResultRow
                ->set('NAME', $template->getName())
                ->set('RELATIVE_PATH', $template->getPath())
                ->set('ABSOLUTE_PATH', $template->getAbsolutePath());
            $this->addOutputFields($loopResultRow, $template);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
