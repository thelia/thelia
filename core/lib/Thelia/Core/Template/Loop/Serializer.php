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

use Thelia\Core\Serializer\SerializerManager;
use Thelia\Core\Serializer\SerializerInterface;
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
 * Class Serializer.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Serializer extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('serializer'),
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
        /** @var SerializerManager $serializerManager */
        $serializerManager = $this->container->get(RegisterSerializerPass::MANAGER_SERVICE_ID);

        $serializerId = $this->getArgValue('serializer');
        $serializers = $serializerId === null ? $serializerManager->getSerializers() : [$serializerManager->get($serializerId)];

        match ($this->getArgValue('order')) {
            'alpha' => ksort($serializers),
            'alpha_reverse' => krsort($serializers),
            default => $serializers,
        };

        return $serializers;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var SerializerInterface $serializer */
        foreach ($loopResult->getResultDataCollection() as $serializer) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set('ID', $serializer->getId())
                ->set('NAME', $serializer->getName())
                ->set('EXTENSION', $serializer->getExtension())
                ->set('MIME_TYPE', $serializer->getMimeType());

            $this->addOutputFields($loopResultRow, $serializer);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
