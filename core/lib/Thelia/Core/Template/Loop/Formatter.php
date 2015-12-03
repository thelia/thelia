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

namespace Thelia\Core\Template\Loop;

use Thelia\Core\DependencyInjection\Compiler\RegisterFormatterPass;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ExportQuery;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class Formatter
 * @package Thelia\Core\Template\Loop
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int getExport()
 * @method string[] getOrder()
 */
class Formatter extends BaseLoop implements ArraySearchLoopInterface
{
    /**
     * this method returns an array
     *
     * @return array
     */
    public function buildArray()
    {
        /** @var \Thelia\Core\FileFormat\Formatting\FormatterManager $service */
        $service = $this->container->get(RegisterFormatterPass::MANAGER_SERVICE_ID);

        $rawFormatters = array_change_key_case($service->getAll());

        $exportId = $this->getExport();
        $formatters = [];

        if ($exportId !== null) {
            $export = ExportQuery::create()->findPk($exportId);

            if (null !== $export) {
                $handlerInstance = $export->getHandleClassInstance($this->container);

                $types = $handlerInstance->getHandledTypes();

                if (is_scalar($types)) {
                    $types = [$types];
                }

                /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
                foreach ($rawFormatters as $key => $formatter) {
                    if (in_array($formatter->getHandledType(), $types)) {
                        $formatters[$key] = $formatter;
                    }
                }
            }
        } else {
            $formatters = $rawFormatters;
        }

        switch ($this->getOrder()) {
            case "alpha":
                ksort($formatters);
                break;
            case "alpha_reverse":
                krsort($formatters);
                break;
        }

        return $formatters;
    }

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Core\FileFormat\Formatting\AbstractFormatter $formatter */
        foreach ($loopResult->getResultDataCollection() as $formatter) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set("NAME", $formatter->getName())
                ->set("EXTENSION", $formatter->getExtension())
                ->set("MIME_TYPE", $formatter->getMimeType())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument("export"),
            new Argument(
                "order",
                new TypeCollection(
                    new EnumType(["alpha", "alpha_reverse"])
                ),
                "alpha"
            )
        );
    }
}
