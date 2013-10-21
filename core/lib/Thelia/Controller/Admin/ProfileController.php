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

use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\ProfileCreationForm;
use Thelia\Form\ProfileModificationForm;
use Thelia\Form\ProfileProfileListUpdateForm;
use Thelia\Model\ProfileQuery;

class ProfileController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'profile',
            'manual',
            'order',

            'admin.configuration.profile.view',
            'admin.configuration.profile.create',
            'admin.configuration.profile.update',
            'admin.configuration.profile.delete',

            TheliaEvents::PROFILE_CREATE,
            TheliaEvents::PROFILE_UPDATE,
            TheliaEvents::PROFILE_DELETE
        );
    }

    protected function getCreationForm()
    {
        return new ProfileCreationForm($this->getRequest());
    }

    protected function getUpdateForm()
    {
        return new ProfileModificationForm($this->getRequest());
    }

    protected function getCreationEvent($formData)
    {
        $event = new ProfileEvent();

        $event->setLocale($formData['locale']);
        $event->setCode($formData['code']);
        $event->setTitle($formData['title']);
        $event->setChapo($formData['chapo']);
        $event->setDescription($formData['description']);
        $event->setPostscriptum($formData['postscriptum']);

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new ProfileEvent();

        $event->setLocale($formData['locale']);
        $event->setId($formData['id']);
        $event->setTitle($formData['title']);
        $event->setChapo($formData['chapo']);
        $event->setDescription($formData['description']);
        $event->setPostscriptum($formData['postscriptum']);

        return $event;
    }

    protected function getDeleteEvent()
    {
        $event = new ProfileEvent();

        $event->setId(
            $this->getRequest()->get('profile_id', 0)
        );

        return $event;
    }

    protected function eventContainsObject($event)
    {
        return $event->hasProfile();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'description'  => $object->getDescription(),
            'code'         => $object->getCode(),
        );

        // Setup the object form
        return new ProfileModificationForm($this->getRequest(), "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasProfile() ? $event->getProfile() : null;
    }

    protected function getExistingObject()
    {
        return ProfileQuery::create()
            ->joinWithI18n($this->getCurrentEditionLocale())
            ->findOneById($this->getRequest()->get('profile_id'));
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
        return array();
    }

    protected function getRouteArguments($profile_id = null)
    {
        return array(
            'profile_id' => $profile_id === null ? $this->getRequest()->get('profile_id') : $profile_id,
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'profiles',
            array()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('profile-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null)
    {
        // We always return to the feature edition form
        $this->redirectToRoute(
            "admin.configuration.profiles.update",
            $this->getViewArguments($country),
            $this->getRouteArguments()
        );
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param  ProfileEvent  $createEvent the create event
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction($createEvent)
    {
        $this->redirectToRoute(
            "admin.configuration.profiles.update",
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getProfile()->getId())
        );
    }

    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
            "admin.configuration.profiles.list"
        );
    }

    protected function checkRequirements($formData)
    {
        $type = $formData['type'];


    }

    protected function getRequirements($type, $formData)
    {
        $requirements = array();
        foreach($formData as $data => $value) {
            if(!strstr($data, ':')) {
                continue;
            }

            $couple = explode(':', $data);

            if(count($couple) != 2 || $couple[0] != $type) {
                continue;
            }

            $requirements[$couple[1]] = $value;
        }

        return $requirements;
    }
}