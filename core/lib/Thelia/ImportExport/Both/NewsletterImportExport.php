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

namespace Thelia\ImportExport\Both;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\ImportExport\ExportHandlerInterface;
use Thelia\ImportExport\ImportHandlerInterface;

/**
 * Class NewsletterImportExport
 * @package Thelia\ImportExport\Both
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class NewsletterImportExport implements ExportHandlerInterface, ImportHandlerInterface
{
    protected $container;

    /**
     * @param ContainerInterface $container
     *
     * Dependency injection: load the container to be able to get parameters and services
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds
     */
    public function buildFormatterData()
    {
        // TODO: Implement buildFormatterData() method.
    }

    /**
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds
     */
    public function importFromFormatterData(FormatterData $data)
    {
        // TODO: Implement importFromFormatterData() method.
    }

} 