<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Thelia\Model\ProductQuery;
use Thelia\Model\Product as ProductModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\ProductUpdateEvent;
use Thelia\Core\Event\ProductCreateEvent;
use Thelia\Core\Event\ProductDeleteEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\ProductToggleVisibilityEvent;
use Thelia\Core\Event\ProductAddContentEvent;
use Thelia\Core\Event\ProductDeleteContentEvent;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\ProductCategory;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Model\TaxQuery;

class Product extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new product entry
     *
     * @param ProductCreateEvent $event
     */
    public function create(ProductCreateEvent $event)
    {
        $product = new ProductModel();

        $product
            ->setDispatcher($this->getDispatcher())

            ->setRef($event->getRef())
            ->setTitle($event->getTitle())
            ->setLocale($event->getLocale())
            ->setVisible($event->getVisible())

            // Set the default tax rule to this product
            ->setTaxRule(TaxRuleQuery::create()->findOneByIsDefault(true))

            ->create($event->getDefaultCategory())
         ;

        $event->setProduct($product);
    }

    /**
     * Change a product
     *
     * @param ProductUpdateEvent $event
     */
    public function update(ProductUpdateEvent $event)
    {
        $search = ProductQuery::create();

        if (null !== $product = ProductQuery::create()->findPk($event->getProductId())) {

            $product
                ->setDispatcher($this->getDispatcher())

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->setParent($event->getParent())
                ->setVisible($event->getVisible())

                ->save();

            $event->setProduct($product);
        }
    }

    /**
     * Delete a product entry
     *
     * @param ProductDeleteEvent $event
     */
    public function delete(ProductDeleteEvent $event)
    {
        if (null !== $product = ProductQuery::create()->findPk($event->getProductId())) {

            $product
                ->setDispatcher($this->getDispatcher())
                ->delete()
            ;

            $event->setProduct($product);
        }
    }

    /**
     * Toggle product visibility. No form used here
     *
     * @param ActionEvent $event
     */
    public function toggleVisibility(ProductToggleVisibilityEvent $event)
    {
         $product = $event->getProduct();

         $product
            ->setDispatcher($this->getDispatcher())
            ->setVisible($product->getVisible() ? false : true)
            ->save()
            ;
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param ProductChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        if (null !== $product = ProductQuery::create()->findPk($event->getObjectId())) {

            $product->setDispatcher($this->getDispatcher());

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE)
                return $product->changeAbsolutePosition($event->getPosition());
            else if ($mode == UpdatePositionEvent::POSITION_UP)
                return $product->movePositionUp();
            else if ($mode == UpdatePositionEvent::POSITION_DOWN)
                return $product->movePositionDown();
        }
    }

    public function addContent(ProductAddContentEvent $event) {

        if (ProductAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
             ->filterByProduct($event->getProduct())->count() <= 0) {

            $content = new ProductAssociatedContent();

            $content
                ->setProduct($event->getProduct())
                ->setContentId($event->getContentId())
                ->save()
            ;
         }
    }

    public function removeContent(ProductDeleteContentEvent $event) {

        $content = ProductAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
            ->filterByProduct($event->getProduct())->findOne()
        ;

        if ($content !== null) $content->delete();
    }


    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::PRODUCT_CREATE            => array("create", 128),
            TheliaEvents::PRODUCT_UPDATE            => array("update", 128),
            TheliaEvents::PRODUCT_DELETE            => array("delete", 128),
            TheliaEvents::PRODUCT_TOGGLE_VISIBILITY => array("toggleVisibility", 128),

            TheliaEvents::PRODUCT_UPDATE_POSITION   => array("updatePosition", 128),

            TheliaEvents::PRODUCT_ADD_CONTENT       => array("addContent", 128),
            TheliaEvents::PRODUCT_REMOVE_CONTENT    => array("removeContent", 128),

        );
    }
}
