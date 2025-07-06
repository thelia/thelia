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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Template as TemplateModel;
use Thelia\Model\TemplateQuery;

/**
 * Template loop.
 *
 * Class Template
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 * @method int[] getExclude()
 */
class ProductTemplate extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
        );
    }

    public function buildModelCriteria(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        $search = TemplateQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['NAME']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var TemplateModel $template */
        foreach ($loopResult->getResultDataCollection() as $template) {
            $loopResultRow = new LoopResultRow($template);

            $loopResultRow
                ->set('ID', $template->getId())
                ->set('IS_TRANSLATED', $template->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('NAME', $template->getVirtualColumn('i18n_NAME'));
            $this->addOutputFields($loopResultRow, $template);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
