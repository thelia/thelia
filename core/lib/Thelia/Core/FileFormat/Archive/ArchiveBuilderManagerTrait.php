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

namespace Thelia\Core\FileFormat\Archive;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait ArchiveBuilderManagerTrait
 * @package Thelia\Core\FileFormat\Archive
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
trait ArchiveBuilderManagerTrait
{
    /**
     * @param  ContainerInterface                                    $container
     * @return \Thelia\Core\FileFormat\Archive\ArchiveBuilderManager
     */
    public function getArchiveBuilderManager(ContainerInterface $container)
    {
        return $container->get("thelia.manager.archive_builder_manager");
    }
}
