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

use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
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
 * Class ArchiveBuilder.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class Archiver extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createBooleanTypeArgument('available'),
            Argument::createAnyTypeArgument('archiver'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumType(['alpha', 'alpha_reverse']),
                ),
                'alpha',
            ),
        );
    }

    public function buildArray(): array
    {
        /** @var ArchiverManager $archiverManager */
        $archiverManager = $this->container->get(RegisterArchiverPass::MANAGER_SERVICE_ID);

        $availability = $this->getArgValue('available');

        $archiverId = $this->getArgValue('archiver');

        if (null === $archiverId) {
            $archivers = $archiverManager->getArchivers($availability);
        } else {
            $archivers = [];
            $archiver = $archiverManager->get($archiverId, $availability);

            if (null !== $archiver) {
                $archivers[] = $archiver;
            }
        }

        match ($this->getArgValue('order')) {
            'alpha' => ksort($archivers),
            'alpha_reverse' => krsort($archivers),
            default => $archivers,
        };

        return $archivers;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var ArchiverInterface $archiver */
        foreach ($loopResult->getResultDataCollection() as $archiver) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set('ID', $archiver->getId())
                ->set('NAME', $archiver->getName())
                ->set('EXTENSION', $archiver->getExtension())
                ->set('MIME_TYPE', $archiver->getMimeType());

            $this->addOutputFields($loopResultRow, $archiver);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
