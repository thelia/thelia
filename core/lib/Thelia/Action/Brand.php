<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandToggleVisibilityEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Model\Brand as BrandModel;
use Thelia\Model\BrandQuery;

/**
 * Class Brand
 *
 * @package Thelia\Action
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class Brand extends BaseAction implements EventSubscriberInterface
{
    public function create(BrandCreateEvent $event)
    {
        $brand = new BrandModel();

        $brand
            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save()
        ;

        $event->setBrand($brand);
    }

    /**
     * process update brand
     *
     * @param $eventName
     */
    public function update(BrandUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $brand = BrandQuery::create()->findPk($event->getBrandId())) {
            $brand
                ->setVisible($event->getVisible())
                ->setLogoImageId(\intval($event->getLogoImageId()) == 0 ? null : $event->getLogoImageId())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save()
            ;

            $event->setBrand($brand);
        }
    }

    /**
     * Toggle Brand visibility
     *
     * @param string $eventName
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function toggleVisibility(BrandToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $brand = $event->getBrand();

        $brand
            ->setVisible(!$brand->getVisible())
            ->save();

        $event->setBrand($brand);
    }

    /**
     * Change Brand SEO
     *
     * @param $eventName
     * @return Object
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdateSeo(BrandQuery::create(), $event, $dispatcher);
    }

    public function delete(BrandDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $brand = BrandQuery::create()->findPk($event->getBrandId())) {
            $brand->delete();

            $event->setBrand($brand);
        }
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(BrandQuery::create(), $event, $dispatcher);
    }

    /**
     * Check if is a brand view and if brand_id is visible
     *
     * @param string $eventName
     */
    public function viewCheck(ViewCheckEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if ($event->getView() == 'brand') {
            $brand = BrandQuery::create()
                ->filterById($event->getViewId())
                ->filterByVisible(1)
                ->count();

            if ($brand == 0) {
                $dispatcher->dispatch($event,TheliaEvents::VIEW_BRAND_ID_NOT_VISIBLE);
            }
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function viewBrandIdNotVisible(ViewCheckEvent $event)
    {
        throw new NotFoundHttpException();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::BRAND_CREATE     => ['create', 128],
            TheliaEvents::BRAND_UPDATE     => ['update', 128],
            TheliaEvents::BRAND_DELETE     => ['delete', 128],

            TheliaEvents::BRAND_UPDATE_SEO => ['updateSeo', 128],

            TheliaEvents::BRAND_UPDATE_POSITION   => ['updatePosition', 128],
            TheliaEvents::BRAND_TOGGLE_VISIBILITY => ['toggleVisibility', 128],

            TheliaEvents::VIEW_CHECK                => ['viewCheck', 128],
            TheliaEvents::VIEW_BRAND_ID_NOT_VISIBLE => ['viewBrandIdNotVisible', 128],
        ];
    }
}
