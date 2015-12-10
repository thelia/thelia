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

use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Archiver extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('available'),
            Argument::createAnyTypeArgument('archiver'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumType(['alpha', 'alpha_reverse'])
                ),
                'alpha'
            )
        );
    }

    public function buildArray()
    {
        /** @var \Thelia\Core\Archiver\ArchiverManager $archiverManager */
        $archiverManager = $this->container->get(RegisterArchiverPass::MANAGER_SERVICE_ID);

        $availability = $this->getArgValue('available');

        $archiverId = $this->getArgValue('archiver');
        if ($archiverId === null) {
            $serializers = $archiverManager->getArchivers($availability);
        } else {
            $serializers = $archiverManager->get($archiverId, $availability);
        }

        switch ($this->getArgValue('order')) {
            case 'alpha':
                ksort($serializers);
                break;
            case 'alpha_reverse':
                krsort($serializers);
                break;
        }

        return $serializers;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Core\Archiver\ArchiverInterface $archiver */
        foreach ($loopResult->getResultDataCollection() as $archiver) {
            $loopResultRow = new LoopResultRow;

            $loopResultRow
                ->set('ID', $archiver->getId())
                ->set('NAME', $archiver->getName())
                ->set('EXTENSION', $archiver->getExtension())
                ->set('MIME_TYPE', $archiver->getMimeType());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
