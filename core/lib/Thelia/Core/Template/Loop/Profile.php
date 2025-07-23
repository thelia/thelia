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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Profile as ProfileModel;
use Thelia\Model\ProfileQuery;

/**
 * Profile loop.
 *
 * Class Profile
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 */
class Profile extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = ProfileQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var ProfileModel $profile */
        foreach ($loopResult->getResultDataCollection() as $profile) {
            $loopResultRow = new LoopResultRow($profile);
            $loopResultRow->set('ID', $profile->getId())
                ->set('IS_TRANSLATED', $profile->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('CODE', $profile->getCode())
                ->set('TITLE', $profile->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $profile->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $profile->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $profile->getVirtualColumn('i18n_POSTSCRIPTUM'));
            $this->addOutputFields($loopResultRow, $profile);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
