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
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;
use Thelia\ImportExport\AbstractHandler;

/**
 * Interface ExportHandler
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ExportHandler extends AbstractHandler
{
    protected $locale;

    /** @var  array */
    protected $order;

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
     * @param \Thelia\Model\Lang $lang
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

            for ($results->rewind(); $results->valid(); $results->next() ) {
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

    public function addI18nCondition(
        ModelCriteria $query,
        $i18nTableName,
        $tableIdColumn,
        $i18nIdColumn,
        $localeColumn,
        $locale
    ) {

        $locale = $this->real_escape($locale);
        $defaultLocale = $this->real_escape($this->defaultLocale);

        $query
            ->_and()
            ->where(
                "CASE WHEN ".$tableIdColumn." IN".
                "(SELECT DISTINCT ".$i18nIdColumn." ".
                "FROM `".$i18nTableName."` ".
                "WHERE locale=$locale) ".

                "THEN ".$localeColumn." = $locale ".
                "ELSE ".$localeColumn." = $defaultLocale ".
                "END"
            )
        ;
    }

    /**
     * @param $str
     * @return string
     *
     * Really escapes a string for SQL request.
     */
    protected function real_escape($str)
    {
        $str = trim($str, "\"'");

        $return = "CONCAT(";
        $len = strlen($str);

        for($i = 0; $i < $len; ++$i) {
            $return .= "CHAR(".ord($str[$i])."),";
        }

        if ($i > 0) {
            $return = substr($return, 0, -1);
        } else {
            $return = "\"\"";
        }
        $return .= ")";

        return $return;
    }

    /**
     * @param Lang $lang
     * @return ModelCriteria|array|BaseLoop
     */
    abstract protected function  buildDataSet(Lang $lang);
} 