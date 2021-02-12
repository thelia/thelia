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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ToggleVisibilityEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Exception\UrlRewritingException;
use Thelia\Form\Exception\FormValidationException;

class BaseAction
{
    /**
     * Changes object position, selecting absolute ou relative change.
     *
     * @param EventDispatcherInterface $dispatcher
     *
     * @return null
     */
    protected function genericUpdatePosition(ModelCriteria $query, UpdatePositionEvent $event, EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {
            if (!isset(class_uses($object)['Thelia\Model\Tools\PositionManagementTrait'])) {
                throw new \InvalidArgumentException('Your model does not implement the PositionManagementTrait trait');
            }

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE) {
                $object->changeAbsolutePosition($event->getPosition());
            } elseif ($mode == UpdatePositionEvent::POSITION_UP) {
                $object->movePositionUp();
            } elseif ($mode == UpdatePositionEvent::POSITION_DOWN) {
                $object->movePositionDown();
            }
        }
    }

    /**
     * @since 2.3
     */
    protected function genericUpdateDelegatePosition(ModelCriteria $query, UpdatePositionEvent $event, EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $object = $query->findOne()) {
            if (!isset(class_uses($object)['Thelia\Model\Tools\PositionManagementTrait'])) {
                throw new \InvalidArgumentException('Your model does not implement the PositionManagementTrait trait');
            }

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE) {
                $object->changeAbsolutePosition($event->getPosition());
            } elseif ($mode == UpdatePositionEvent::POSITION_UP) {
                $object->movePositionUp();
            } elseif ($mode == UpdatePositionEvent::POSITION_DOWN) {
                $object->movePositionDown();
            }
        }
    }

    /**
     * Changes SEO Fields for an object.
     *
     * @param EventDispatcherInterface $dispatcher
     *
     * @return mixed an SEOxxx object
     *
     * @throws FormValidationException if a rewritten URL cannot be created
     */
    protected function genericUpdateSeo(ModelCriteria $query, UpdateSeoEvent $event, EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {
            $object
                ->setLocale($event->getLocale())
                ->setMetaTitle($event->getMetaTitle())
                ->setMetaDescription($event->getMetaDescription())
                ->setMetaKeywords($event->getMetaKeywords())
                ->save()
            ;

            // Update the rewritten URL, if required
            try {
                $object->setRewrittenUrl($event->getLocale(), $event->getUrl());
            } catch (UrlRewritingException $e) {
                throw new FormValidationException($e->getMessage(), $e->getCode());
            }

            $event->setObject($object);
        }

        return $object;
    }

    /**
     * Toggle visibility for an object.
     *
     * @param EventDispatcherInterface $dispatcher
     */
    public function genericToggleVisibility(ModelCriteria $query, ToggleVisibilityEvent $event, EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {
            $newVisibility = !$object->getVisible();
            $object
                ->setVisible($newVisibility)
                ->save()
            ;

            $event->setObject($object);
        }

        return $object;
    }
}
