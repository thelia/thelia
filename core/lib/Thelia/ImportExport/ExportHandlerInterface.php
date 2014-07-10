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

namespace Thelia\ImportExport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface ExportHandlerInterface
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
interface ExportHandlerInterface
{
    /**
     * @param ContainerInterface $container
     *
     * Dependency injection: load the container to be able to get parameters and services
     */
    public function __construct(ContainerInterface $container);

    /**
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds
     */
    public function buildFormatterData();
} 