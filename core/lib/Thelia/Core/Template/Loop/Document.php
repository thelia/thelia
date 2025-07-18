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
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * The document loop.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method int[]       getExclude()
 * @method bool|string getVisible()
 * @method int         getLang()
 * @method int         getCategory()
 * @method int         getProduct()
 * @method int         getFolder()
 * @method int         getContent()
 * @method string      getSource()
 * @method int         getSourceId()
 * @method bool        getNewsletter()
 * @method string      getQueryNamespace()
 * @method bool        getWithPrevNextInfo()
 * @method string[]    getOrder()
 */
class Document extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $objectType;
    protected $objectId;
    protected $timestampable = true;

    /** @var array Possible standard document sources */
    protected array $possible_sources = ['category', 'product', 'folder', 'content', 'brand'];

    protected function getArgDefinitions(): ArgumentCollection
    {
        $collection = new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random']),
                ),
                'manual',
            ),
            Argument::createIntTypeArgument('lang'),
            Argument::createIntTypeArgument('category'),
            Argument::createIntTypeArgument('product'),
            Argument::createIntTypeArgument('folder'),
            Argument::createIntTypeArgument('content'),
            Argument::createAnyTypeArgument('source'),
            Argument::createIntTypeArgument('source_id'),
            Argument::createBooleanTypeArgument('force_return', true),
            Argument::createAnyTypeArgument('query_namespace', 'Thelia\\Model'),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
        );

        // Add possible document sources
        foreach ($this->possible_sources as $source) {
            $collection->addArgument(Argument::createIntTypeArgument($source));
        }

        return $collection;
    }

    /**
     * Dynamically create the search query, and set the proper filter and order.
     *
     * @param string $source    a valid source identifier (@see $possible_sources)
     * @param int    $object_id the source object ID
     *
     * @return ModelCriteria the propel Query object
     */
    protected function createSearchQuery(string $source, int $object_id): ModelCriteria
    {
        $object = ucfirst($source);

        $ns = $this->getQueryNamespace();

        if ('\\' !== $ns[0]) {
            $ns = '\\'.$ns;
        }

        $queryClass = \sprintf('%s\\%sDocumentQuery', $ns, $object);
        $filterMethod = \sprintf('filterBy%sId', $object);

        // xxxDocumentQuery::create()
        $method = new \ReflectionMethod($queryClass, 'create');
        $search = $method->invoke(null);
        // Static !
        // $query->filterByXXX(id)
        $method = new \ReflectionMethod($queryClass, $filterMethod);
        $method->invoke($search, $object_id);

        $orders = $this->getOrder();

        // Results ordering
        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        return $search;
    }

    /**
     * Dynamically create the search query, and set the proper filter and order.
     *
     * @param string $object_type (returned) the a valid source identifier (@see $possible_sources)
     * @param string $object_id   (returned) the ID of the source object
     *
     * @return ModelCriteria the propel Query object
     */
    protected function getSearchQuery(?string &$object_type, ?string &$object_id): ModelCriteria
    {
        $search = null;

        // Check form source="product" source_id="123" style arguments
        $source = $this->getSource();

        if (null !== $source) {
            $source_id = $this->getSourceId();
            $id = $this->getId();

            if (null === $source_id && null === $id) {
                throw new \InvalidArgumentException("If 'source' argument is specified, 'id' or 'source_id' argument should be specified");
            }

            $search = $this->createSearchQuery($source, (int) $source_id);

            $object_type = $source;
            $object_id = (string) $source_id;
        } else {
            // Check for product="id" folder="id", etc. style arguments
            foreach ($this->possible_sources as $source) {
                $argValue = (int) $this->getArgValue($source);

                if ($argValue > 0) {
                    $search = $this->createSearchQuery($source, $argValue);

                    $object_type = $source;
                    $object_id = (string) $argValue;

                    break;
                }
            }
        }

        return $search;
    }

    public function buildModelCriteria(): ModelCriteria
    {
        // Select the proper query to use, and get the object type
        $this->objectType = null;
        $this->objectId = null;
        /** @var ProductDocumentQuery $search */
        $search = $this->getSearchQuery($this->objectType, $this->objectId);

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if (BooleanOrBothType::ANY !== $visible) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $baseSourceFilePath = ConfigQuery::read('documents_library_path');

        if (null === $baseSourceFilePath) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'documents';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        /** @var ProductDocument $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            // Create document processing event
            $event = new DocumentEvent();

            // Put source document file path
            $sourceFilePath = \sprintf(
                '%s/%s/%s',
                $baseSourceFilePath,
                $this->objectType,
                $result->getFile(),
            );

            $event->setSourceFilepath($sourceFilePath);
            $event->setCacheSubdirectory($this->objectType);

            try {
                // Dispatch document processing event
                $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_PROCESS);

                $loopResultRow = new LoopResultRow($result);

                $loopResultRow
                    ->set('ID', $result->getId())
                    ->set('LOCALE', $this->locale)
                    ->set('DOCUMENT_FILE', $result->getFile())
                    ->set('DOCUMENT_URL', $event->getDocumentUrl())
                    ->set('DOCUMENT_PATH', $event->getDocumentPath())
                    ->set('ORIGINAL_DOCUMENT_PATH', $sourceFilePath)
                    ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                    ->set('CHAPO', $result->getVirtualColumn('i18n_CHAPO'))
                    ->set('DESCRIPTION', $result->getVirtualColumn('i18n_DESCRIPTION'))
                    ->set('POSTSCRIPTUM', $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                    ->set('VISIBLE', $result->getVisible())
                    ->set('POSITION', $result->getPosition())
                    ->set('OBJECT_TYPE', $this->objectType)
                    ->set('OBJECT_ID', $this->objectId);

                $isBackendContext = $this->getBackendContext();

                if ($this->getWithPrevNextInfo()) {
                    $previousQuery = $this->getSearchQuery($this->objectType, $this->objectId)
                        ->filterByPosition($result->getPosition(), Criteria::LESS_THAN);

                    if (!$isBackendContext) {
                        $previousQuery->filterByVisible(true);
                    }

                    $previous = $previousQuery
                        ->orderByPosition(Criteria::DESC)
                        ->findOne();
                    $nextQuery = $this->getSearchQuery($this->objectType, $this->objectId)
                        ->filterByPosition($result->getPosition(), Criteria::GREATER_THAN);

                    if (!$isBackendContext) {
                        $nextQuery->filterByVisible(true);
                    }

                    $next = $nextQuery
                        ->orderByPosition(Criteria::ASC)
                        ->findOne();
                    $loopResultRow
                        ->set('HAS_PREVIOUS', null !== $previous ? 1 : 0)
                        ->set('HAS_NEXT', null !== $next ? 1 : 0)
                        ->set('PREVIOUS', null !== $previous ? $previous->getId() : -1)
                        ->set('NEXT', null !== $next ? $next->getId() : -1);
                }

                $this->addOutputFields($loopResultRow, $result);

                $loopResult->addRow($loopResultRow);
            } catch (\Exception $ex) {
                // Ignore the result and log an error
                Tlog::getInstance()->addError(\sprintf('Failed to process document in document loop: %s', $ex->getMessage()));
            }
        }

        return $loopResult;
    }
}
