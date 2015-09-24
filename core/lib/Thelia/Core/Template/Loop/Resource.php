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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\ResourceQuery;
use Thelia\Type;
use Thelia\Model\Resource as ResourceModel;

/**
 *
 * Resource loop
 *
 *
 * Class Resource
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getProfile()
 * @method string[] getCode()
 */
class Resource extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('profile'),
            new Argument(
                'code',
                new Type\TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = ResourceQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $profile = $this->getProfile();

        if (null !== $profile) {
            $search->leftJoinProfileResource('profile_resource')
                ->addJoinCondition('profile_resource', 'profile_resource.PROFILE_ID=?', $profile, null, \PDO::PARAM_INT)
                ->withColumn('profile_resource.access', 'access');
        }

        $code = $this->getCode();

        if (null !== $code) {
            $search->filterByCode($code, Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ResourceModel $resource */
        foreach ($loopResult->getResultDataCollection() as $resource) {
            $loopResultRow = new LoopResultRow($resource);
            $loopResultRow->set("ID", $resource->getId())
                ->set("IS_TRANSLATED", $resource->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("CODE", $resource->getCode())
                ->set("TITLE", $resource->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $resource->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $resource->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $resource->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;

            if (null !== $this->getProfile()) {
                $accessValue = $resource->getVirtualColumn('access');
                $manager = new AccessManager($accessValue);

                $loopResultRow->set("VIEWABLE", $manager->can(AccessManager::VIEW)? 1 : 0)
                    ->set("CREATABLE", $manager->can(AccessManager::CREATE) ? 1 : 0)
                    ->set("UPDATABLE", $manager->can(AccessManager::UPDATE)? 1 : 0)
                    ->set("DELETABLE", $manager->can(AccessManager::DELETE)? 1 : 0);
            }

            $this->addOutputFields($loopResultRow, $resource);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
