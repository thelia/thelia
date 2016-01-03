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

        $event->setTaxRule($taxRule)->setId($taxRule->getId());
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

            $taxList = $this->getArrayFromJson($event->getTaxList());
            $countryList = $this->getArrayFromJson($event->getCountryList());
            $countryDeletedList = $this->getArrayFromJson($event->getCountryDeletedList());

            /* clean the current tax rule for the countries/states */
            $deletes = array_merge($countryList, $countryDeletedList);
            foreach ($deletes as $item) {
                TaxRuleCountryQuery::create()
                    ->filterByTaxRule($taxRule)
                    ->filterByCountryId(intval($item[0]), Criteria::EQUAL)
                    ->filterByStateId(intval($item[1]) !== 0 ? $item[1] : null, Criteria::EQUAL)
                    ->delete();
            }

            /* for each country */
            foreach ($countryList as $item) {
                $position = 1;
                $countryId = intval($item[0]);
                $stateId = intval($item[1]);

                /* on applique les nouvelles regles */
                foreach ($taxList as $tax) {
                    if (is_array($tax)) {
                        foreach ($tax as $samePositionTax) {
                            $taxModel = new TaxRuleCountry();
                            $taxModel->setTaxRule($taxRule)
                                ->setCountryId($countryId)
                                ->setStateId($stateId ?: null)
                                ->setTaxId($samePositionTax)
                                ->setPosition($position);
                            $taxModel->save();
                        }
                    } else {
                        $taxModel = new TaxRuleCountry();
                        $taxModel->setTaxRule($taxRule)
                            ->setCountryId($countryId)
                            ->setStateId($stateId ?: null)
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

    protected function getArrayFromJson($obj)
    {
        return is_array($obj)
            ? $obj
            : json_decode($obj, true);
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
