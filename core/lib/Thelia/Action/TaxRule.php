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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Model\TaxRule as TaxRuleModel;
use Thelia\Model\TaxRuleQuery;

class TaxRule extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param TaxRuleEvent $event
     */
    public function create(TaxRuleEvent $event)
    {
        $taxRule = new TaxRuleModel();

        $taxRule
            ->setDispatcher($event->getDispatcher())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
         ;

        $taxRule->save();

        $event->setTaxRule($taxRule);
    }

    /**
     * @param TaxRuleEvent $event
     */
    public function update(TaxRuleEvent $event)
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {

            $taxRule
                ->setDispatcher($event->getDispatcher())
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
    public function updateTaxes(TaxRuleEvent $event)
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {

            $taxList = json_decode($event->getTaxList(), true);

            /* clean the current tax rule for the countries */
            TaxRuleCountryQuery::create()
                ->filterByTaxRule($taxRule)
                ->filterByCountryId($event->getCountryList(), Criteria::IN)
                ->delete();

            /* for each country */
            foreach ($event->getCountryList() as $country) {
                $position = 1;
                /* on applique les nouvelles regles */
                foreach ($taxList as $tax) {
                    if (is_array($tax)) {
                        foreach ($tax as $samePositionTax) {
                            $taxModel = new TaxRuleCountry();
                            $taxModel->setTaxRule($taxRule)
                                ->setCountryId($country)
                                ->setTaxId($samePositionTax)
                                ->setPosition($position);
                            $taxModel->save();
                        }
                    } else {
                        $taxModel = new TaxRuleCountry();
                        $taxModel->setTaxRule($taxRule)
                            ->setCountryId($country)
                            ->setTaxId($tax)
                            ->setPosition($position);
                        $taxModel->save();
                    }
                    $position++;
                }
            }

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
     * @param TaxRuleEvent $event
     */
    public function setDefault(TaxRuleEvent $event)
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {

            TaxRuleQuery::create()->update(array(
                "IsDefault" => 0
            ));

            $taxRule->setIsDefault(1)->save();

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
            TheliaEvents::TAX_RULE_TAXES_UPDATE      => array("updateTaxes", 128),
            TheliaEvents::TAX_RULE_DELETE            => array("delete", 128),
            TheliaEvents::TAX_RULE_SET_DEFAULT       => array("setDefault", 128),
        );
    }
}
