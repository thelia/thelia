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

namespace Thelia\Core\Event\ImportExport;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\ImportExport\Export\ExportHandler;

/**
 * Class Export
 * @package Thelia\Core\Event\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class Export extends ActionEvent
{
    /** @var  \Thelia\ImportExport\Export\ExportHandler */
    protected $handler;

    /** @var  \Thelia\Core\FileFormat\Formatting\AbstractFormatter */
    protected $formatter;

    /** @var  \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder */
    protected $archiveBuilder;

    public function __construct(
        AbstractFormatter $formatter,
        \Thelia\ImportExport\Export\ExportHandler $handler,
        AbstractArchiveBuilder $archiveBuilder = null
    ) {
        $this->archiveBuilder = $archiveBuilder;
        $this->formatter = $formatter;
        $this->handler = $handler;
    }

    /**
     * @param  AbstractArchiveBuilder $archiveBuilder
     * @return $this
     */
    public function setArchiveBuilder(AbstractArchiveBuilder $archiveBuilder)
    {
        $this->archiveBuilder = $archiveBuilder;

        return $this;
    }

    /**
     * @return \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder
     */
    public function getArchiveBuilder()
    {
        return $this->archiveBuilder;
    }

    /**
     * @param  AbstractFormatter $formatter
     * @return $this
     */
    public function setFormatter(AbstractFormatter $formatter)
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @return \Thelia\Core\FileFormat\Formatting\AbstractFormatter
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * @param  \Thelia\ImportExport\Export\ExportHandler $handler
     * @return $this
     */
    public function setHandler(ExportHandler $handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * @return \Thelia\ImportExport\Export\ExportHandler
     */
    public function getHandler()
    {
        return $this->handler;
    }
}
