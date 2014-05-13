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

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\Event\Api\ApiCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Api\ApiCreateForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ApiQuery;


/**
 * Class ApiController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ApiController extends BaseAdminController
{

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::API], [], AccessManager::VIEW)) {
            return $response;
        }

        return $this->renderList();
    }

    public function createAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::API], [], AccessManager::CREATE)) {
            return $response;
        }

        $form = new ApiCreateForm($this->getRequest());
        $error_msg = null;
        try {

            $createForm = $this->validateForm($form);

            $event = new ApiCreateEvent(
                $createForm->get('label')->getData(),
                $createForm->get('profile')->getData() ?: null
            );

            $this->dispatch(TheliaEvents::API_CREATE, $event);

            return RedirectResponse::create($form->getSuccessUrl());

        } catch(FormValidationException $e) {
            $error_msg = $this->createStandardFormValidationErrorMessage($e);
        } catch(\Exception $e) {
            $error_msg = $e->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj creation", array('%obj' => 'Api')),
                $error_msg,
                $form,
                $e
            );

            // At this point, the form has error, and should be redisplayed.
            return $this->renderList();
        }
    }

    protected function renderList()
    {
        $apiAccessList = ApiQuery::create()->find()->toArray();
        return $this->render(
            'api',
            [
                'api_list' => $apiAccessList
            ]
        );
    }

}