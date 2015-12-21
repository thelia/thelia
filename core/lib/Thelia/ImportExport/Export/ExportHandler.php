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

namespace Thelia\ImportExport\Export;

use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidValueException;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\Exception\LoopException;
use Thelia\Core\Translation\Translator;
use Thelia\Files\FileModelInterface;
use Thelia\Model\Lang;
use Thelia\ImportExport\AbstractHandler;

/**
 * Interface ExportHandler
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ExportHandler extends AbstractHandler
{
    /** @var string Default filename. */
    const DEFAULT_FILENAME = "data";

    protected $locale;

    /** @var  array */
    protected $order = array();

    protected $isImageExport = false;

    protected $isDocumentExport = false;

    protected $rangeDate = null;

    /**
     * @return array
     *
     * You may override this method to return an array, containing
     * the order that you want to have for your columns.
     * The order appliance depends on the formatter
     */
    protected function getDefaultOrder()
    {
        return array();
    }

    /**
     * @return null|array
     *
     * You may override this method to return an array, containing
     * the aliases to use.
     */
    protected function getAliases()
    {
        return null;
    }

    /**
     * @return array
     *
     * Use this method to access the order.
     *
     */
    public function getOrder()
    {
        $order = $this->getDefaultOrder();

        if (empty($order)) {
            $order = $this->order;
        }

        return $order;
    }

    public function setOrder(array $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return array
     *
     * Use this method to access the range date.
     *
     */
    public function getRangeDate()
    {
        return $this->rangeDate;
    }

    /**
     * Use this method to set the range date
     *
     * @param array $rangeDate
     * @return $this
     */
    public function setRangeDate(array $rangeDate)
    {
        if (isset($rangeDate["start"]) && isset($rangeDate["end"])) {
            $this->rangeDate = $rangeDate;
        }
        return $this;
    }

    /**
     * @param  \Thelia\Model\Lang                               $lang
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds the FormatterData for the formatter
     */
    public function buildData(Lang $lang)
    {
        $data = new FormatterData($this->getAliases());

        $query = $this->buildDataSet($lang);

        if ($query instanceof ModelCriteria) {
            return $data->loadModelCriteria($query);
        } elseif (is_array($query)) {
            return $data->setData($query);
        } elseif ($query instanceof BaseLoop) {
            $pagination = null;
            $results = $query->exec($pagination);

            for ($results->rewind(); $results->valid(); $results->next()) {
                $current = $results->current();

                $data->addRow($current->getVarVal());
            }

            return $data;
        }

        throw new InvalidValueException(
            Translator::getInstance()->trans(
                "The method \"%class\"::buildDataSet must return an array or a ModelCriteria",
                [
                    "%class" => get_class($this),
                ]
            )
        );
    }

    public function renderLoop($type, array $args = array())
    {
        $loopsDefinition = $this->container->getParameter("thelia.parser.loops");

        if (!isset($loopsDefinition[$type])) {
            throw new LoopException(
                Translator::getInstance()->trans(
                    "The loop \"%loop\" doesn't exist",
                    [
                        "%loop" => $type
                    ]
                )
            );
        }

        $reflection = new \ReflectionClass($loopsDefinition[$type]);

        if (!$reflection->isSubclassOf("Thelia\\Core\\Template\\Element\\BaseLoop")) {
            throw new LoopException(
                Translator::getInstance()->trans(
                    "The class \"%class\" must be a subclass of %baseClass",
                    [
                        "%class" => $loopsDefinition[$type],
                        "%baseClass" => "Thelia\\Core\\Template\\Element\\BaseLoop",
                    ]
                )
            );
        }

        /** @var BaseLoop $loopInstance */
        $loopInstance = $reflection->newInstance($this->container);

        $loopInstance->initializeArgs($args);

        return $loopInstance;
    }

    protected function addFileToArray(FileModelInterface $model, array &$paths)
    {
        $path = $model->getUploadDir() . DS . $model->getFile();

        if (is_file($path) && is_readable($path)) {
            $parent = $model->getParentFileModel();

            $name = constant($parent::TABLE_MAP . "::TABLE_NAME");
            $paths[$name . DS . $model->getFile()] = $path;
        }
    }

    public function setDocumentExport($bool)
    {
        $this->isDocumentExport = (bool) $bool;

        return $this;
    }

    public function setImageExport($bool)
    {
        $this->isImageExport = (bool) $bool;

        return $this;
    }

    public function isDocumentExport()
    {
        return $this->isDocumentExport;
    }

    public function isImageExport()
    {
        return $this->isImageExport;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return static::DEFAULT_FILENAME;
    }

    /**
     * @param  Lang                         $lang
     * @return ModelCriteria|array|BaseLoop
     */
    abstract public function buildDataSet(Lang $lang);
}
