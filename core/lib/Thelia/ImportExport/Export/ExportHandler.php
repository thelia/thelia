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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\Lang;
use Thelia\ImportExport\AbstractHandler;

/**
 * Interface ExportHandler
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ExportHandler extends AbstractHandler
{
    public function addI18nCondition(
        ModelCriteria $query,
        $i18nTableName,
        $tableIdColumn,
        $i18nIdColumn,
        $localeColumn,
        $locale
    ) {

        $locale = $this->real_escape($locale);
        $defaultLocale = $this->real_escape(Lang::getDefaultLanguage()->getLocale());

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
     * @param \Thelia\Model\Lang $lang
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds the FormatterData for the formatter
     */
    abstract public function buildFormatterData(Lang $lang);

} 