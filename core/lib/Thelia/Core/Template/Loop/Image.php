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
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImage;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * The image loop.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method int         getWidth()
 * @method int         getHeight()
 * @method int         getRotation()
 * @method string      getBackgroundColor()
 * @method int         getQuality()
 * @method string      getEffects()
 * @method int         getCategory()
 * @method int         getProduct()
 * @method int         getFolder()
 * @method int         getContent()
 * @method string      getSource()
 * @method ?string     getSourceId()
 * @method string      getQueryNamespace()
 * @method bool        getAllowZoom()
 * @method bool        getIgnoreProcessingErrors()
 * @method string      getResizeMode()
 * @method bool        getBase64()
 * @method bool        getWithPrevNextInfo()
 * @method string      getFormat()
 * @method string[]    getOrder()
 */
class Image extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $objectType;
    protected $objectId;
    protected $timestampable = true;

    /** @var array Possible standard image sources */
    protected array $possible_sources = ['category', 'product', 'folder', 'content', 'module', 'brand'];

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
            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createIntTypeArgument('rotation', 0),
            Argument::createAnyTypeArgument('background_color'),
            Argument::createIntTypeArgument('quality'),
            new Argument(
                'resize_mode',
                new TypeCollection(
                    new EnumType(['crop', 'borders', 'none']),
                ),
                'none',
            ),
            Argument::createAnyTypeArgument('effects'),
            Argument::createIntTypeArgument('category'),
            Argument::createIntTypeArgument('product'),
            Argument::createIntTypeArgument('folder'),
            Argument::createIntTypeArgument('content'),
            Argument::createAnyTypeArgument('source'),
            Argument::createIntTypeArgument('source_id'),
            Argument::createBooleanTypeArgument('force_return', true),
            Argument::createBooleanTypeArgument('ignore_processing_errors', true),
            Argument::createAnyTypeArgument('query_namespace', 'Thelia\\Model'),
            Argument::createBooleanTypeArgument('allow_zoom', false),
            Argument::createBooleanTypeArgument('base64', false),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createAnyTypeArgument('format'),
        );

        // Add possible image sources
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
    protected function createSearchQuery(string $source, mixed $object_id): ModelCriteria
    {
        $object = ucfirst($source);

        $ns = $this->getQueryNamespace();

        if ('\\' !== $ns[0]) {
            $ns = '\\'.$ns;
        }

        $queryClass = \sprintf('%s\\%sImageQuery', $ns, $object);
        $filterMethod = \sprintf('filterBy%sId', $object);

        // xxxImageQuery::create()
        $method = new \ReflectionMethod($queryClass, 'create');
        $search = $method->invoke(null);

        // Static !
        if (null !== $object_id) {
            // $query->filterByXXX(id)
            $method = new \ReflectionMethod($queryClass, $filterMethod);
            $method->invoke($search, $object_id);
        }

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
     * @param string $objectType (returned) the a valid source identifier (@see $possible_sources)
     * @param string $objectId   (returned) the ID of the source object
     *
     * @return ModelCriteria the propel Query object
     */
    protected function getSearchQuery(?string &$objectType, string|int|null &$objectId): ModelCriteria
    {
        $search = null;

        // Check form source="product" source_id="123" style arguments
        $source = $this->getSource();

        if (null !== $source) {
            $sourceId = $this->getSourceId();
            $id = $this->getId();

            if (null === $sourceId && null === $id) {
                throw new \InvalidArgumentException("If 'source' argument is specified, 'id' or 'source_id' argument should be specified");
            }

            $sourceId = (int) ($sourceId ?? $id);
            $search = $this->createSearchQuery($source, $sourceId);

            $objectType = $source;
            $objectId = $sourceId;
        } else {
            // Check for product="id" folder="id", etc. style arguments
            foreach ($this->possible_sources as $source) {
                $argValue = $this->getArgValue($source);

                if (!empty($argValue)) {
                    $argValue = (int) $argValue;

                    $search = $this->createSearchQuery($source, $argValue);

                    $objectType = $source;
                    $objectId = $argValue;

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
        // Create image processing event
        $event = new ImageEvent();

        // Prepare tranformations
        $width = $this->getWidth();
        $height = $this->getHeight();
        $rotation = $this->getRotation();
        $background_color = $this->getBackgroundColor();
        $quality = $this->getQuality();
        $effects = $this->getEffects();
        $format = $this->getFormat();

        $event->setAllowZoom($this->getAllowZoom());

        if (null !== $effects) {
            $effects = explode(',', $effects);
        }

        $resizeMode = match ($this->getResizeMode()) {
            'crop' => \Thelia\Action\Image::EXACT_RATIO_WITH_CROP,
            'borders' => \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS,
            default => \Thelia\Action\Image::KEEP_IMAGE_RATIO,
        };

        $baseSourceFilePath = ConfigQuery::read('images_library_path');

        if (null === $baseSourceFilePath) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        /** @var ProductImage $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            // Setup required transformations
            if (null !== $width) {
                $event->setWidth((int) $width);
            }

            if (null !== $height) {
                $event->setHeight((int) $height);
            }

            $event->setResizeMode((string) $resizeMode);

            if (null !== $rotation) {
                $event->setRotation((int) $rotation);
            }

            if (null !== $background_color) {
                $event->setBackgroundColor($background_color);
            }

            if (null !== $quality) {
                $event->setQuality($quality);
            }

            if (null !== $effects) {
                $event->setEffects($effects);
            }

            if (null !== $format) {
                $event->setFormat($format);
            }

            // Put source image file path
            $sourceFilePath = \sprintf(
                '%s/%s/%s',
                $baseSourceFilePath,
                $this->objectType,
                $result->getFile(),
            );

            $event->setSourceFilepath($sourceFilePath);
            $event->setCacheSubdirectory($this->objectType);

            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set('ID', $result->getId())
                ->set('LOCALE', $this->locale)
                ->set('ORIGINAL_IMAGE_PATH', $sourceFilePath)
                ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $result->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $result->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('VISIBLE', $result->getVisible())
                ->set('POSITION', $result->getPosition())
                ->set('OBJECT_TYPE', $this->objectType)
                ->set('OBJECT_ID', $this->objectId);

            $addRow = true;

            $returnErroredImages = $this->getBackendContext() || !$this->getIgnoreProcessingErrors();

            try {
                // Dispatch image processing event
                $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

                $imageExt = pathinfo($event->getSourceFilepath(), \PATHINFO_EXTENSION);

                $loopResultRow
                    ->set('IMAGE_URL', $event->getFileUrl())
                    ->set('ORIGINAL_IMAGE_URL', $event->getOriginalFileUrl())
                    ->set('IMAGE_PATH', $event->getCacheFilepath())
                    ->set('PROCESSING_ERROR', false)
                    ->set('IS_SVG', 'svg' === $imageExt)
                    ->set('IMAGE_HEIGHT', $event->getImageObject()?->getSize()->getHeight())
                    ->set('IMAGE_WIDTH', $event->getImageObject()?->getSize()->getWidth());

                if ($this->getBase64()) {
                    $loopResultRow->set('IMAGE_BASE64', $this->toBase64($event->getCacheFilepath()));
                }
            } catch (\Exception $ex) {
                // Ignore the result and log an error
                Tlog::getInstance()->addError(\sprintf('Failed to process image in image loop: %s', $ex->getMessage()));

                if ($returnErroredImages) {
                    $loopResultRow
                        ->set('IMAGE_URL', '')
                        ->set('ORIGINAL_IMAGE_URL', '')
                        ->set('IMAGE_PATH', '')
                        ->set('PROCESSING_ERROR', true)
                        ->set('IMAGE_HEIGHT', '')
                        ->set('IMAGE_WIDTH', '');
                } else {
                    $addRow = false;
                }
            }

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

            if ($addRow) {
                $this->addOutputFields($loopResultRow, $result);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }

    private function toBase64(string $path): string
    {
        $imgData = base64_encode(file_get_contents($path));

        return $src = 'data: '.mime_content_type($path).';base64,'.$imgData;
    }
}
