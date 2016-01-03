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
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * {@inheritdoc}
 * @method string getAllowedArchiveBuilder()
 * @method string[] getOrder()
 */
class ArchiveBuilder extends BaseLoop implements ArraySearchLoopInterface
{
    /**
     * this method returns an array
     *
     * @return array
     */
    public function buildArray()
    {
        /** @var \Thelia\Core\FileFormat\Archive\archiveBuilderManager $service */
        $service = $this->container->get("thelia.manager.archive_builder_manager");

        $rawArchiveBuilders = array_change_key_case($service->getAll());

        $allowedArchiveBuilder = $this->getAllowedArchiveBuilder();
        $archiveBuilders = [];

        if ($allowedArchiveBuilder !== null) {
            $allowedArchiveBuilder = explode(",", $allowedArchiveBuilder);

            foreach ($allowedArchiveBuilder as $archiveBuilder) {
                $archiveBuilder = trim(strtolower($archiveBuilder));

                if (isset($rawArchiveBuilders[$archiveBuilder])) {
                    $archiveBuilders[$archiveBuilder] = $rawArchiveBuilders[$archiveBuilder];
                }
            }
        } else {
            $archiveBuilders = $rawArchiveBuilders;
        }

        switch ($this->getOrder()) {
            case "alpha":
                ksort($archiveBuilders);
                break;
            case "alpha_reverse":
                krsort($archiveBuilders);
                break;
        }

        return $archiveBuilders;
    }

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Core\FileFormat\Archive\AbstractarchiveBuilder $archiveBuilder */
        foreach ($loopResult->getResultDataCollection() as $archiveBuilder) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set("NAME", $archiveBuilder->getName())
                ->set("EXTENSION", $archiveBuilder->getExtension())
                ->set("MIME_TYPE", $archiveBuilder->getMimeType())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument("allowed_archive_builder"),
            new Argument(
                "order",
                new TypeCollection(
                    new EnumType(["alpha", "alpha_reverse"])
                ),
                "alpha"
            )
        );
    }
}
