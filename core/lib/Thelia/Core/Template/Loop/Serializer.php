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

use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class Serializer
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Serializer extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('export'),
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
        /** @var \Thelia\Core\Serializer\SerializerManager $serializerManager */
        $serializerManager = $this->container->get(RegisterSerializerPass::MANAGER_SERVICE_ID);

        $exportId = $this->getArgValue('export');
        if ($exportId !== null) {
            $serializers = $serializerManager->getSerializers();
        } else {
            $serializers = $serializerManager->get($exportId);
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
        /** @var \Thelia\Core\Serializer\SerializerInterface $serializer */
        foreach ($loopResult->getResultDataCollection() as $serializer) {
            $loopResultRow = new LoopResultRow;

            $loopResultRow
                ->set('ID', $serializer->getId())
                ->set('NAME', $serializer->getName())
                ->set('EXTENSION', $serializer->getExtension())
                ->set('MIME_TYPE', $serializer->getMimeType());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
