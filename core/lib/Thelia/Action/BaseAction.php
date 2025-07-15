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

namespace Thelia\Action;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ToggleVisibilityEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Exception\UrlRewritingException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Tools\PositionManagementTrait;

class BaseAction
{
    /**
     * Changes object position, selecting absolute ou relative change.
     */
    protected function genericUpdatePosition(ModelCriteria $query, UpdatePositionEvent $event, ?EventDispatcherInterface $dispatcher = null): void
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {
            if (!isset(class_uses($object)[PositionManagementTrait::class])) {
                throw new \InvalidArgumentException('Your model does not implement the PositionManagementTrait trait');
            }

            $mode = $event->getMode();

            if (UpdatePositionEvent::POSITION_ABSOLUTE === $mode) {
                $object->changeAbsolutePosition($event->getPosition());
            } elseif (UpdatePositionEvent::POSITION_UP === $mode) {
                $object->movePositionUp();
            } elseif (UpdatePositionEvent::POSITION_DOWN === $mode) {
                $object->movePositionDown();
            }
        }
    }

    protected function genericUpdateDelegatePosition(ModelCriteria $query, UpdatePositionEvent $event, ?EventDispatcherInterface $dispatcher = null): void
    {
        if (null !== $object = $query->findOne()) {
            if (!isset(class_uses($object)[PositionManagementTrait::class])) {
                throw new \InvalidArgumentException('Your model does not implement the PositionManagementTrait trait');
            }

            $mode = $event->getMode();

            if (UpdatePositionEvent::POSITION_ABSOLUTE === $mode) {
                $object->changeAbsolutePosition($event->getPosition());
            } elseif (UpdatePositionEvent::POSITION_UP === $mode) {
                $object->movePositionUp();
            } elseif (UpdatePositionEvent::POSITION_DOWN === $mode) {
                $object->movePositionDown();
            }
        }
    }

    /**
     * Changes SEO Fields for an object.
     *
     * @return mixed an SEOxxx object
     *
     * @throws FormValidationException if a rewritten URL cannot be created
     */
    protected function genericUpdateSeo(ModelCriteria $query, UpdateSeoEvent $event, ?EventDispatcherInterface $dispatcher = null): mixed
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {
            $object
                ->setLocale($event->getLocale())
                ->setMetaTitle($event->getMetaTitle())
                ->setMetaDescription($event->getMetaDescription())
                ->setMetaKeywords($event->getMetaKeywords())
                ->save();

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
     */
    public function genericToggleVisibility(ModelCriteria $query, ToggleVisibilityEvent $event, ?EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {
            $newVisibility = !$object->getVisible();
            $object
                ->setVisible($newVisibility)
                ->save();

            $event->setObject($object);
        }

        return $object;
    }
}
