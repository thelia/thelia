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

namespace Carousel\Loop;

use Carousel\Model\CarouselQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Image;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class CarouselLoop
 * @package Carousel\Loop
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class CarouselLoop extends Image
{


    /**
     * @inheritdoc
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createIntTypeArgument('rotation', 0),
            Argument::createAnyTypeArgument('background_color'),
            Argument::createIntTypeArgument('quality'),
            new Argument(
                'resize_mode',
                new TypeCollection(
                    new EnumType(array('crop', 'borders', 'none'))
                ),
                'none'
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(array('alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random'))
                ),
                'manual'
            ),
            Argument::createAnyTypeArgument('effects'),
            Argument::createBooleanTypeArgument('allow_zoom', false)
        );
    }

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Carousel\Model\Carousel $carousel */
        foreach ($loopResult->getResultDataCollection() as $carousel) {
            $loopResultRow = new LoopResultRow($carousel);

            $event = new ImageEvent();
            $event->setSourceFilepath($carousel->getUploadDir() . DS . $carousel->getFile())
                ->setCacheSubdirectory('carousel');

            switch ($this->getResizeMode()) {
                case 'crop':
                    $resize_mode = \Thelia\Action\Image::EXACT_RATIO_WITH_CROP;
                    break;

                case 'borders':
                    $resize_mode = \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS;
                    break;

                case 'none':
                default:
                    $resize_mode = \Thelia\Action\Image::KEEP_IMAGE_RATIO;

            }

            // Prepare tranformations
            $width = $this->getWidth();
            $height = $this->getHeight();
            $rotation = $this->getRotation();
            $background_color = $this->getBackgroundColor();
            $quality = $this->getQuality();
            $effects = $this->getEffects();

            if (!is_null($width)) {
                $event->setWidth($width);
            }
            if (!is_null($height)) {
                $event->setHeight($height);
            }
            $event->setResizeMode($resize_mode);
            if (!is_null($rotation)) {
                $event->setRotation($rotation);
            }
            if (!is_null($background_color)) {
                $event->setBackgroundColor($background_color);
            }
            if (!is_null($quality)) {
                $event->setQuality($quality);
            }
            if (!is_null($effects)) {
                $event->setEffects($effects);
            }

            $event->setAllowZoom($this->getAllowZoom());

            // Dispatch image processing event
            $this->dispatcher->dispatch(TheliaEvents::IMAGE_PROCESS, $event);

            $loopResultRow
                ->set('ID', $carousel->getId())
                ->set("LOCALE", $this->locale)
                ->set("IMAGE_URL", $event->getFileUrl())
                ->set("ORIGINAL_IMAGE_URL", $event->getOriginalFileUrl())
                ->set("IMAGE_PATH", $event->getCacheFilepath())
                ->set("ORIGINAL_IMAGE_PATH", $event->getSourceFilepath())
                ->set("TITLE", $carousel->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $carousel->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $carousel->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $carousel->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("ALT", $carousel->getVirtualColumn('i18n_ALT'))
                ->set("URL", $carousel->getUrl())
                ->set('POSITION', $carousel->getPosition())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        $search = CarouselQuery::create();

        $this->configureI18nProcessing($search, [ 'ALT', 'TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM' ]);

        $orders  = $this->getOrder();

        // Results ordering
        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual-reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "random":
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break(2);
                    break;
            }
        }

        return $search;
    }
}
