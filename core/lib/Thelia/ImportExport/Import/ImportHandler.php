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

namespace Thelia\ImportExport\Import;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\AbstractHandler;

/**
 * Class ImportHandler
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ImportHandler extends AbstractHandler
{
    protected $importedRows = 0;

    /** @var Translator */
    protected $translator;

    public function __construct(ContainerInterface $container)
    {
        $this->translator = Translator::getInstance();

        parent::__construct($container);
    }

    public function getImportedRows()
    {
        return $this->importedRows;
    }

    protected function checkMandatoryColumns(array $row)
    {
        $mandatoryColumns = $this->getMandatoryColumns();
        sort($mandatoryColumns);

        $diff = [];

        foreach ($mandatoryColumns as $name) {
            if (!isset($row[$name]) || empty($row[$name])) {
                $diff[] = $name;
            }
        }

        if (!empty($diff)) {
            throw new \UnexpectedValueException(
                $this->translator->trans(
                    "The following columns are missing: %columns",
                    [
                        "%columns" => implode(", ", $diff),
                    ]
                )
            );
        }
    }

    /**
     * @return array The mandatory columns to have for import
     */
    abstract protected function getMandatoryColumns();

    /**
     * @param \Thelia\Core\FileFormat\Formatting\FormatterData
     * @return string|array error messages
     *
     * The method does the import routine from a FormatterData
     */
    abstract public function retrieveFromFormatterData(FormatterData $data);
}
