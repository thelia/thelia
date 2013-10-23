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

use Thelia\Core\Event\Lang\LangUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Lang\LangUpdateForm;
use Thelia\Model\LangQuery;


/**
 * Class LangController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class LangController extends BaseAdminController
{

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, AccessManager::VIEW)) return $response;

        return $this->render('languages');
    }

    public function updateAction($lang_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, AccessManager::UPDATE)) return $response;

        $this->checkXmlHttpRequest();

        $lang = LangQuery::create()->findPk($lang_id);

        $langForm = new LangUpdateForm($this->getRequest(), 'form', array(
            'id' => $lang->getId(),
            'title' => $lang->getTitle(),
            'code' => $lang->getCode(),
            'locale' => $lang->getLocale(),
            'date_format' => $lang->getDateFormat(),
            'time_format' => $lang->getTimeFormat()
        ));

        $this->getParserContext()->addForm($langForm);

        return $this->render('ajax/language-update-modal', array(
            'lang_id' => $lang_id
        ));
    }

    public function processUpdateAction($lang_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, AccessManager::UPDATE)) return $response;

        $error_msg = false;

        $langForm = new LangUpdateForm($this->getRequest());

        try {
            $form = $this->validateForm($langForm);

            $event = new LangUpdateEvent($form->get('id')->getData());
            $event
                ->setTitle($form->get('title')->getData())
                ->setCode($form->get('code')->getData())
                ->setLocale($form->get('locale')->getData())
                ->setDateFormat($form->get('date_format')->getData())
                ->setTimeFormat($form->get('time_format')->getData())
            ;

            $this->dispatch(TheliaEvents::LANG_UPDATE, $event);

            if (false === $event->hasLang()) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', 'Lang')));
            }

            $changedObject = $event->getLang();
            $this->adminLogAppend(sprintf("%s %s (ID %s) modified", 'Lang', $changedObject->getTitle(), $changedObject->getId()));
            $this->redirectToRoute('/admin/configuration/languages');
        } catch(\Exception $e) {
            $error_msg = $e->getMessage();
        }

        return $this->render('languages');
    }

    public function toggleDefaultAction($lang_id)
    {

    }
}