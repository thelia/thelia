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

namespace Thelia\Core\Event;

use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\FileFormat\Formatting\AbstractSerializer;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\ImportExport\AbstractHandler;
use Thelia\ImportExport\Export\ExportHandler;

/**
 * Class Export
 * @package Thelia\Core\Event\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportExport extends ActionEvent
{
    /** @var  \Thelia\ImportExport\AbstractHandler */
    protected $handler;

    /** @var  \Thelia\Core\FileFormat\Formatting\AbstractSerializer */
    protected $formatter;

    /** @var  FormatterData */
    protected $data;

    /** @var  \Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder */
    protected $archiveBuilder;

    /** @var  mixed */
    protected $content;

    public function __construct(
        AbstractSerializer $formatter = null,
        AbstractHandler $handler = null,
        FormatterData $data = null,
        AbstractArchiveBuilder $archiveBuilder = null
    ) {
        $this->archiveBuilder = $archiveBuilder;
        $this->formatter = $formatter;
        $this->handler = $handler;
        $this->data = $data;
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
     * @param  AbstractSerializer $formatter
     * @return $this
     */
    public function setFormatter(AbstractSerializer $formatter)
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @return \Thelia\Core\FileFormat\Formatting\AbstractSerializer
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

    /**
     * @param  FormatterData $data
     * @return $this
     */
    public function setData(FormatterData $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    public function isArchive()
    {
        return $this->archiveBuilder !== null;
    }
}
