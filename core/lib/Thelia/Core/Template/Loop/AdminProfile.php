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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\AdminProfileQuery;
use Thelia\Model\AdminProfile as AdminProfileModel;
use Thelia\Log\Tlog;

/**
 *
 * AdminProfile loop
 *
 *
 * Class AdminProfile
 * @package Thelia\Core\Template\Loop
 * @author Julien Vigouroux <jvigouroux@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 */
class AdminProfile extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('admin'),
            Argument::createIntListTypeArgument('profile')
        );
    }

    public function buildModelCriteria()
    {
        $search = AdminProfileQuery::create();

        if (null !== $admin = $this->getAdmin()) {
            $search->filterByAdminId($admin, Criteria::IN);
        }

        if (null !== $profile = $this->getProfile()) {
            $search->filterByProfileId($admin, Criteria::IN);
        }

        $search->orderByAdminId(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var AdminProfileModel $profile */
        foreach ($loopResult->getResultDataCollection() as $profile) {
            $loopResultRow = new LoopResultRow($profile);
            $loopResultRow->set("ADMINID", $profile->getAdminId())
                ->set("PROFILEID", $profile->getProfileId())
            ;
            $this->addOutputFields($loopResultRow, $profile);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
