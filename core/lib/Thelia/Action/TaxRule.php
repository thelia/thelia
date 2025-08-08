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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\TaxRule as TaxRuleModel;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Model\TaxRuleQuery;

class TaxRule extends BaseAction implements EventSubscriberInterface
{
    public function create(TaxRuleEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $taxRule = new TaxRuleModel();

        $taxRule
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
        ;

        $taxRule->save();

        $event->setTaxRule($taxRule)->setId($taxRule->getId());
    }

    public function update(TaxRuleEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
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

    public function updateTaxes(TaxRuleEvent $event): void
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {
            $taxList = $this->getArrayFromJson($event->getTaxList());
            $countryList = $this->getArrayFromJson22Compat($event->getCountryList());
            $countryDeletedList = $this->getArrayFromJson22Compat($event->getCountryDeletedList());

            /* clean the current tax rule for the countries/states */
            $deletes = array_merge($countryList, $countryDeletedList);
            foreach ($deletes as $item) {
                TaxRuleCountryQuery::create()
                    ->filterByTaxRule($taxRule)
                    ->filterByCountryId((int) $item[0], Criteria::EQUAL)
                    ->filterByStateId((int) $item[1] !== 0 ? $item[1] : null, Criteria::EQUAL)
                    ->delete();
            }

            /* for each country */
            foreach ($countryList as $item) {
                $position = 1;
                $countryId = (int) $item[0];
                $stateId = (int) $item[1];

                /* on applique les nouvelles regles */
                foreach ($taxList as $tax) {
                    if (\is_array($tax)) {
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
                    ++$position;
                }
            }

            $event->setTaxRule($taxRule);
        }
    }

    protected function getArrayFromJson($obj)
    {
        if (null === $obj) {
            $obj = [];
        } else {
            $obj = \is_array($obj)
                ? $obj
                : json_decode($obj, true);
        }

        return $obj;
    }

    /**
     * This method ensures compatibility with the 2.2.x country arrays passed throught the TaxRuleEvent.
     *
     * In 2.2.x, the TaxRuleEvent::getXXXCountryList() methods returned an array of country IDs. [ country ID, country ID ...].
     * From 2.3.0-alpha1, these functions are expected to return an array of arrays, each one containing a country ID and
     * a state ID. [ [ country ID, state ID], [ country ID, state ID], ...].
     *
     * This method checks the $obj parameter, and create a 2.3.0-alpha1 compatible return value if $obj is expressed using
     * the 2.2.x form.
     *
     * @param array $obj
     *
     * @return array
     */
    protected function getArrayFromJson22Compat($obj)
    {
        $obj = $this->getArrayFromJson($obj);

        if (isset($obj[0]) && !\is_array($obj[0])) {
            $objEx = [];
            foreach ($obj as $item) {
                $objEx[] = [$item, 0];
            }

            return $objEx;
        }

        return $obj;
    }

    public function delete(TaxRuleEvent $event): void
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {
            $taxRule
                ->delete()
            ;

            $event->setTaxRule($taxRule);
        }
    }

    public function setDefault(TaxRuleEvent $event): void
    {
        if (null !== $taxRule = TaxRuleQuery::create()->findPk($event->getId())) {
            TaxRuleQuery::create()->update([
                'IsDefault' => 0,
            ]);

            $taxRule->setIsDefault(1)->save();

            $event->setTaxRule($taxRule);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::TAX_RULE_CREATE => ['create', 128],
            TheliaEvents::TAX_RULE_UPDATE => ['update', 128],
            TheliaEvents::TAX_RULE_TAXES_UPDATE => ['updateTaxes', 128],
            TheliaEvents::TAX_RULE_DELETE => ['delete', 128],
            TheliaEvents::TAX_RULE_SET_DEFAULT => ['setDefault', 128],
        ];
    }
}
