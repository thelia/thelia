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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\TaxRuleCreationForm;
use Thelia\Form\TaxRuleModificationForm;
use Thelia\Model\CountryQuery;
use Thelia\Model\TaxRuleQuery;

class TaxRuleController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'taxrule',
            'manual',
            'order',

            'admin.configuration.taxrule.view',
            'admin.configuration.taxrule.create',
            'admin.configuration.taxrule.update',
            'admin.configuration.taxrule.delete',

            TheliaEvents::TAX_RULE_CREATE,
            TheliaEvents::TAX_RULE_UPDATE,
            TheliaEvents::TAX_RULE_DELETE
        );
    }

    protected function getCreationForm()
    {
        return new TaxRuleCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new TaxRuleModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $event = new TaxRuleEvent();

        /* @todo fill event */

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new TaxRuleEvent();

        /* @todo fill event */

        return $event;
    }

    protected function getDeleteEvent()
    {
        $event = new TaxRuleEvent();

        /* @todo fill event */

        return $event;
    }

    protected function eventContainsObject($event)
    {
        return $event->hasTaxRule();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'description'  => $object->getDescription(),
        );

        // Setup the object form
        return new TaxRuleModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasTaxRule() ? $event->getTaxRule() : null;
    }

    protected function getExistingObject()
    {
        return TaxRuleQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->findOneById($this->getRequest()->get('tax_rule_id'));
    }

    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments()
    {
        return array(
            'tax_rule_id' => $this->getRequest()->get('tax_rule_id'),
            'country_isoalpha3' => $this->getRequest()->get('country_isoalpha3'),
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'taxes-rules',
            array()
        );
    }

    protected function renderEditionTemplate()
    {
        /* check the country exists */
        $country = CountryQuery::create()->findOneByIsoalpha3($this->getRequest()->get('country_isoalpha3'));
        if(null === $country) {
            $this->redirectToListTemplate();
        }

        // We always return to the feature edition form
        return $this->render('tax-rule-edit', $this->getViewArguments());
    }

    protected function redirectToEditionTemplate()
    {
        // We always return to the feature edition form
        $this->redirectToRoute(
            "admin.configuration.taxes-rules.update",
            $this->getViewArguments()
        );
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
            "admin.configuration.taxes-rules.list",
            array()
        );
    }

}