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

namespace Thelia\Tests\Controller;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarBz2ArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarGzArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\ZipArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManager;
use Thelia\Core\FileFormat\Formatting\Formatter\CSVFormatter;
use Thelia\Core\FileFormat\Formatting\Formatter\JsonFormatter;
use Thelia\Core\FileFormat\Formatting\Formatter\XMLFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterManager;

/**
 * Class ControllerExportControllerTest
 * @package Thelia\Tests\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
trait ImportExportControllerTrait
{
    /**
     * @return mixed
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $cacheDir = THELIA_CACHE_DIR . "test";
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        $archiveBuilders = [
            new ZipArchiveBuilder(),
            new TarArchiveBuilder(),
            new TarBz2ArchiveBuilder(),
            new TarGzArchiveBuilder(),
        ];

        $archiveBuilderManager = new ArchiveBuilderManager("test");

        /**
         * @var \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder $archiveBuilder
         */
        foreach ($archiveBuilders as $key => $archiveBuilder) {
            if (!$archiveBuilder->isAvailable()) {
                unset($archiveBuilders[$key]);
            } else {
                $archiveBuilderManager->add($archiveBuilder);
            }
        }

        if (empty($archiveBuilders)) {
            $this->markTestSkipped("You don't have any archive builder, you can't run this test");
        }

        $container->set("thelia.manager.archive_builder_manager", $archiveBuilderManager);

        $formatterManager = (new FormatterManager())
            ->add(new XMLFormatter())
            ->add(new JsonFormatter())
            ->add(new CSVFormatter())
        ;

        $container->set("thelia.manager.formatter_manager", $formatterManager);
    }
}
