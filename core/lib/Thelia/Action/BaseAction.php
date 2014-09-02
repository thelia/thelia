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
namespace Thelia\Action;

use Propel\Runtime\ActiveQuery\ModelCriteria;

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
     * @param ModelCriteria       $query
     * @param UpdatePositionEvent $event
     *
     * @return null
     */
    protected function genericUpdatePosition(ModelCriteria $query, UpdatePositionEvent $event)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {

            $object->setDispatcher($event->getDispatcher());

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE)
                $object->changeAbsolutePosition($event->getPosition());
            else if ($mode == UpdatePositionEvent::POSITION_UP)
                $object->movePositionUp();
            else if ($mode == UpdatePositionEvent::POSITION_DOWN)
                $object->movePositionDown();
        }
    }

    /**
     * Changes SEO Fields for an object.
     *
     * @param ModelCriteria  $query
     * @param UpdateSeoEvent $event
     *
     * @return mixed                   an SEOxxx object
     * @throws FormValidationException if a rewritten URL cannot be created
     */
    protected function genericUpdateSeo(ModelCriteria $query, UpdateSeoEvent $event)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {

            $object
                ->setDispatcher($event->getDispatcher())

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
     * Toggle visibility for an object
     *
     * @param ModelCriteria               $query
     * @param UpdateToggleVisibilityEvent $event
     *
     * @return mixed
     */
    public function genericToggleVisibility(ModelCriteria $query, ToggleVisibilityEvent $event)
    {
        if (null !== $object = $query->findPk($event->getObjectId())) {

            $newVisibility = ($object->getVisible() === 1) ? 0 : 1;
            $object
                ->setDispatcher($event->getDispatcher())

                ->setVisible($newVisibility)

                ->save()
            ;

            $event->setObject($object);
        }

        return $object;
    }

}
