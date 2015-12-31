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

namespace Thelia\Controller\Api;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\TaxRule;
use Thelia\Model\Map\TaxRuleCountryTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Model\TaxRuleI18nQuery;
use Thelia\Model\TaxRuleQuery;

/**
 * Class TaxRuleController
 * @package Thelia\Controller\Api
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author manuel raynaud <manu@raynaud.io>
 */
class TaxRuleController extends AbstractCrudApiController
{
    public function __construct()
    {
        parent::__construct(
            "tax rule",
            AdminResources::TAX,
            [TheliaEvents::TAX_RULE_CREATE, TheliaEvents::TAX_RULE_TAXES_UPDATE],
            [TheliaEvents::TAX_RULE_UPDATE, TheliaEvents::TAX_RULE_TAXES_UPDATE],
            TheliaEvents::TAX_RULE_DELETE
        );
    }


    /**
     * @return \Thelia\Core\Template\Element\BaseLoop
     *
     * Get the entity loop instance
     */
    protected function getLoop()
    {
        return new TaxRule($this->container);
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     */
    protected function getCreationForm(array $data = array())
    {
        return $this->createForm(null, "tax_rule", $data);
    }

    /**
     * @param array $data
     * @return \Thelia\Form\BaseForm
     */
    protected function getUpdateForm(array $data = array())
    {
        return $this->createForm(null, "tax_rule", $data, array(
            "validation_groups" => ["Default", "update"],
            "method" => "PUT",
        ));
    }

    /**
     * @param Event $event
     * @return null|mixed
     *
     * Get the object from the event
     *
     * if return null or false, the action will throw a 404
     */
    protected function extractObjectFromEvent(Event $event)
    {
        return $event->getTaxRule();
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getCreationEvent(array &$data)
    {
        return $this->hydrateEvent($data);
    }

    /**
     * @param array $data
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getUpdateEvent(array &$data)
    {
        return $this->hydrateEvent($data);
    }

    /**
     * @param mixed $entityId
     * @return \Symfony\Component\EventDispatcher\Event
     */
    protected function getDeleteEvent($entityId)
    {
        $data = ["id" => $entityId];

        return $this->hydrateEvent($data);
    }

    protected function afterCreateEvents(Event $event, array &$data)
    {
        $dispatcher = $this->getDispatcher();

        if ($data["default"]) {
            $dispatcher->dispatch(TheliaEvents::TAX_RULE_SET_DEFAULT, $event);
        }

        foreach ($data["i18n"] as $i18nRow) {
            $this->hydrateI18nEvent($i18nRow, $event);

            foreach ($this->updateEvents as $eventName) {
                $dispatcher->dispatch($eventName, $event);
            }
        }
    }

    protected function afterUpdateEvents(Event $event, array &$data)
    {
        $this->afterCreateEvents($event, $data);
    }

    protected function hydrateEvent(array &$data)
    {
        $event = new TaxRuleEvent();

        if (isset($data["country"])) {
            $event->setCountryList($data["country"]);
        }

        if (isset($data["tax"])) {
            $event->setTaxList($data["tax"]);
        }

        if (isset($data["id"])) {
            $event->setId($data["id"]);
            $event->setTaxRule(TaxRuleQuery::create()->findPk($data["id"]));
        }

        if (isset($data["i18n"]) && null !== $row = array_shift($data["i18n"])) {
            $this->hydrateI18nEvent($row, $event);
        }

        return $event;
    }

    protected function hydrateI18nEvent(array $i18nRow, TaxRuleEvent $event)
    {
        $event
            ->setLocale($i18nRow["locale"])
            ->setTitle($i18nRow["title"])
            ->setDescription($i18nRow["description"])
        ;
    }

    public function hydrateUpdateForm(FormEvent $event)
    {
        $data = $event->getData();
        $keys = array_keys($data["i18n"]);

        foreach ($keys as $key) {
            $value = $data["i18n"][$key];
            $data["i18n"][$value["locale"]] = $value;

            unset($data["i18n"][$key]);
        }

        $persistentI18n = $this->getI18nPersistentData($data["id"]);

        foreach ($persistentI18n["i18n"] as $locale => $value) {
            $data["i18n"][$locale] = array_merge($value, $data["i18n"][$locale]);
        }

        $data = array_merge($this->getPersistentData($data["id"]), $data);

        $event->setData($data);
    }

    protected function getPersistentData($taxRuleId)
    {
        $taxRule = TaxRuleQuery::create()->findPk($taxRuleId);

        if (null === $taxRule) {
            throw new HttpException(404, json_encode([
                "error" => sprintf("The tax rule %d doesn't exist", $taxRuleId)
            ]));
        }

        $countries = TaxRuleCountryQuery::create()
            ->filterByTaxRuleId($taxRuleId)
            ->distinct()
            ->select(TaxRuleCountryTableMap::COUNTRY_ID)
            ->find()
            ->toArray()
        ;

        $taxes = TaxRuleCountryQuery::create()
            ->filterByTaxRuleId($taxRuleId)
            ->distinct()
            ->select(TaxRuleCountryTableMap::TAX_ID)
            ->find()
            ->toArray()
        ;

        $data = [
            "default" => (bool) $taxRule->getIsDefault(),
            "tax" => $taxes,
            "country" => $countries,
        ];

        return $data;
    }

    protected function getI18nPersistentData($taxRuleId)
    {
        $i18ns = TaxRuleI18NQuery::create()
            ->findById($taxRuleId)
        ;

        $data["i18n"] = array();

        /** @var \Thelia\Model\TaxRuleI18n $i18n */
        foreach ($i18ns as $i18n) {
            $data["i18n"][$i18n->getLocale()] = array(
                "locale" => $i18n->getLocale(),
                "title" => $i18n->getTitle(),
                "description" => $i18n->getDescription(),
            );
        }

        return $data;
    }
}
