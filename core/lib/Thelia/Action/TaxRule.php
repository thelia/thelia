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
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\TaxRule as TaxRuleModel;
use Thelia\Model\TaxRuleQuery;

class TaxRule extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param TaxRuleEvent $event
     */
    public function create(TaxRuleEvent $event)
    {
        $product = new TaxRuleModel();

        $product
            ->setDispatcher($this->getDispatcher())

            ->setRef($event->getRef())
            ->setTitle($event->getTitle())
            ->setLocale($event->getLocale())
            ->setVisible($event->getVisible())

            // Set the default tax rule to this product
            ->setTaxRule(TaxRuleQuery::create()->findOneByIsDefault(true))

            //public function create($defaultCategoryId, $basePrice, $priceCurrencyId, $taxRuleId, $baseWeight) {

            ->create(
                    $event->getDefaultCategory(),
                    $event->getBasePrice(),
                    $event->getCurrencyId(),
                    $event->getTaxRuleId(),
                    $event->getBaseWeight()
            );
         ;

        $event->setTaxRule($product);
    }

    /**
     * @param TaxRuleEvent $event
     */
    public function update(TaxRuleEvent $event)
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {

            $taxRule
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->save()
            ;



            $event->setTaxRule($taxRule);
        }
    }

    /**
     * @param TaxRuleEvent $event
     */
    public function delete(TaxRuleEvent $event)
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {

            $taxRule
                ->delete()
            ;

            $event->setTaxRule($taxRule);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::TAX_RULE_CREATE            => array("create", 128),
            TheliaEvents::TAX_RULE_UPDATE            => array("update", 128),
            TheliaEvents::TAX_RULE_DELETE            => array("delete", 128),

        );
    }
}
