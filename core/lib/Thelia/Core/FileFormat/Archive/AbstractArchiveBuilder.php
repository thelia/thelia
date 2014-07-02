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
use Thelia\Core\FileFormat\FormatInterface;
use Thelia\Tools\FileDownload\FileDownloaderAwareTrait;

/**
 * Class AbstractArchiveBuilder
 * @package Thelia\Core\FileFormat\Archive
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractArchiveBuilder implements FormatInterface, ArchiveBuilderInterface
{
    use FileDownloaderAwareTrait;
} 