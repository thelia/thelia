<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Log\Tlog;
use Thelia\Model\Export;
use Thelia\Model\ExportQUery;
use Thelia\Model\Import as ImportModel;
use Thelia\Model\ImportQuery;
use Thelia\Tools\URL;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class ImportExportType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * @method int[]|null    getId()
 * @method string[]|null getRef()
 * @method int[]|null    getCategory()
 * @method string[]      getOrder()
 */
abstract class ImportExportType extends BaseI18nLoop implements PropelSearchLoopInterface
{
    public const DEFAULT_ORDER = 'manual';

    protected $timestampable = true;

    /**
     * @throws PropelException
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var ImportModel|Export $type */
        foreach ($loopResult->getResultDataCollection() as $type) {
            $loopResultRow = new LoopResultRow($type);

            $url = URL::getInstance()->absoluteUrl(
                $this->getBaseUrl().'/'.$type->getId(),
            );

            try {
                $loopResultRow
                    ->set('HANDLE_CLASS', $type->getHandleClass())
                    ->set('ID', $type->getId())
                    ->set('REF', $type->getRef())
                    ->set('TITLE', $type->getVirtualColumn('i18n_TITLE'))
                    ->set('DESCRIPTION', $type->getVirtualColumn('i18n_DESCRIPTION'))
                    ->set('URL', $url)
                    ->set('POSITION', $type->getPosition())
                    ->set('CATEGORY_ID', $type->getByName($this->getCategoryName()));
            } catch (\Exception $e) {
                Tlog::getInstance()->error($e->getMessage());
            }

            $this->addOutputFields($loopResultRow, $type);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria.
     */
    public function buildModelCriteria(): ModelCriteria
    {
        /** @var ImportQuery|ExportQUery $query */
        $query = $this->getQueryModel();

        $this->configureI18nProcessing($query, ['TITLE', 'DESCRIPTION']);

        if (null !== $ids = $this->getId()) {
            $query->filterById($ids);
        }

        if (null !== $refs = $this->getRef()) {
            $query->filterByRef($refs);
        }

        if (null !== $categories = $this->getCategory()) {
            $query->filterBy($this->getCategoryName(), $categories, Criteria::IN);
        }

        if (null !== $orders = $this->getOrder()) {
            foreach ($orders as $order) {
                switch ($order) {
                    case 'id':
                        $query->orderById();
                        break;
                    case 'id_reverse':
                        $query->orderById(Criteria::DESC);
                        break;
                    case 'ref':
                        $query->orderByRef();
                        break;
                    case 'ref_reverse':
                        $query->orderByRef(Criteria::DESC);
                        break;
                    case 'alpha':
                        $query->addAscendingOrderByColumn('i18n_TITLE');
                        break;
                    case 'alpha_reverse':
                        $query->addDescendingOrderByColumn('i18n_TITLE');
                        break;
                    case 'manual':
                        $query->orderByPosition();
                        break;
                    case 'manual_reverse':
                        $query->orderByPosition(Criteria::DESC);
                        break;
                }
            }
        }

        return $query;
    }

    /**
     * Definition of loop arguments.
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
     */
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('category'),
            Argument::createAnyListTypeArgument('ref'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(static::getAllowedOrders()),
                ),
                static::DEFAULT_ORDER,
            ),
        );
    }

    public static function getAllowedOrders(): array
    {
        return ['id', 'id_reverse', 'ref', 'ref_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse'];
    }

    abstract protected function getBaseUrl(): string;

    abstract protected function getQueryModel(): ImportQuery|ExportQUery;

    abstract protected function getCategoryName(): string;
}
