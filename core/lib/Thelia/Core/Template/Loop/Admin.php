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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Admin as AdminModel;
use Thelia\Model\AdminQuery;

/**
 * Admin loop.
 *
 * Class Admin
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 * @method int[] getProfile()
 */
class Admin extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('profile'),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = AdminQuery::create();

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $profile = $this->getProfile();

        if (null !== $profile) {
            $search->filterByProfileId($profile, Criteria::IN);
        }

        $search->orderByFirstname(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var AdminModel $admin */
        foreach ($loopResult->getResultDataCollection() as $admin) {
            $loopResultRow = new LoopResultRow($admin);
            $loopResultRow->set('ID', $admin->getId())
                ->set('PROFILE', $admin->getProfileId())
                ->set('FIRSTNAME', $admin->getFirstname())
                ->set('LASTNAME', $admin->getLastname())
                ->set('LOGIN', $admin->getLogin())
                ->set('LOCALE', $admin->getLocale())
                ->set('EMAIL', $admin->getEmail());
            $this->addOutputFields($loopResultRow, $admin);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
