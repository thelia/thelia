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

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\Lang\LangCreateEvent;
use Thelia\Core\Event\Lang\LangDefaultBehaviorEvent;
use Thelia\Core\Event\Lang\LangDeleteEvent;
use Thelia\Core\Event\Lang\LangEvent;
use Thelia\Core\Event\Lang\LangToggleActiveEvent;
use Thelia\Core\Event\Lang\LangToggleDefaultEvent;
use Thelia\Core\Event\Lang\LangToggleVisibleEvent;
use Thelia\Core\Event\Lang\LangUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\Lang\LangUrlEvent;
use Thelia\Form\Lang\LangUrlForm;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class LangController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangController extends BaseAdminController
{
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::VIEW)) {
            return $response;
        }
        return $this->renderDefault();
    }

    public function renderDefault(array $param = array(), $status = 200)
    {
        $data = array();
        foreach (LangQuery::create()->find() as $lang) {
            $data[LangUrlForm::LANG_PREFIX.$lang->getId()] = $lang->getUrl();
        }
        $langUrlForm = $this->createForm(AdminForm::LANG_URL, 'form', $data);
        $this->getParserContext()->addForm($langUrlForm);

        return $this->render('languages', array_merge($param, array(
            'lang_without_translation' => ConfigQuery::getDefaultLangWhenNoTranslationAvailable(),
            'one_domain_per_lang' => ConfigQuery::isMultiDomainActivated()
        )), $status);
    }

    public function updateAction($lang_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $this->checkXmlHttpRequest();

        $lang = LangQuery::create()->findPk($lang_id);

        $langForm = $this->createForm(AdminForm::LANG_UPDATE, 'form', array(
            'id' => $lang->getId(),
            'title' => $lang->getTitle(),
            'code' => $lang->getCode(),
            'locale' => $lang->getLocale(),
            'date_format' => $lang->getDateFormat(),
            'time_format' => $lang->getTimeFormat(),
            'decimal_separator' => $lang->getDecimalSeparator(),
            'thousands_separator' => $lang->getThousandsSeparator(),
            'decimals' => $lang->getDecimals(),
        ));

        $this->getParserContext()->addForm($langForm);

        return $this->render('ajax/language-update-modal', array(
            'lang_id' => $lang_id
        ));
    }

    public function processUpdateAction($lang_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = false;

        $langForm = $this->createForm(AdminForm::LANG_UPDATE);

        try {
            $form = $this->validateForm($langForm);

            $event = new LangUpdateEvent($form->get('id')->getData());
            $event = $this->hydrateEvent($event, $form);

            $this->dispatch(TheliaEvents::LANG_UPDATE, $event);

            if (false === $event->hasLang()) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', 'Lang'))
                );
            }

            /** @var Lang $changedObject */
            $changedObject = $event->getLang();
            $this->adminLogAppend(
                AdminResources::LANGUAGE,
                AccessManager::UPDATE,
                sprintf(
                    "%s %s (ID %s) modified",
                    'Lang',
                    $changedObject->getTitle(),
                    $changedObject->getId()
                ),
                $changedObject->getId()
            );

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (\Exception $ex) {
            $error_msg = $this->getTranslator()->trans("Failed to update language definition: %ex", array("%ex" => $ex->getMessage()));
            Tlog::getInstance()->addError("Failed to update language definition", $ex->getMessage());
        }

        if (false !== $error_msg) {
            $response = $this->renderDefault(array('error_message' => $error_msg));
        }

        return $response;
    }

    /**
     * @param LangCreateEvent $event
     * @param Form $form
     * @return LangCreateEvent
     */
    protected function hydrateEvent($event, Form $form)
    {
        return $event
            ->setTitle($form->get('title')->getData())
            ->setCode($form->get('code')->getData())
            ->setLocale($form->get('locale')->getData())
            ->setDateFormat($form->get('date_format')->getData())
            ->setTimeFormat($form->get('time_format')->getData())
            ->setDecimalSeparator($form->get('decimal_separator')->getData())
            ->setThousandsSeparator($form->get('thousands_separator')->getData())
            ->setDecimals($form->get('decimals')->getData())
        ;
    }

    public function toggleDefaultAction($lang_id)
    {
        return $this->toggleLangDispatch(
            TheliaEvents::LANG_TOGGLEDEFAULT,
            new LangToggleDefaultEvent($lang_id)
        );
    }

    public function toggleActiveAction($lang_id)
    {
        return $this->toggleLangDispatch(
            TheliaEvents::LANG_TOGGLEACTIVE,
            new LangToggleActiveEvent($lang_id)
        );
    }

    public function toggleVisibleAction($lang_id)
    {
        return $this->toggleLangDispatch(
            TheliaEvents::LANG_TOGGLEVISIBLE,
            new LangToggleVisibleEvent($lang_id)
        );
    }

    public function addAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::CREATE)) {
            return $response;
        }

        $createForm = $this->createForm(AdminForm::LANG_CREATE);

        $error_msg = false;
        $ex = null;

        try {
            $form = $this->validateForm($createForm);

            $createEvent = new LangCreateEvent();
            $createEvent = $this->hydrateEvent($createEvent, $form);

            $this->dispatch(TheliaEvents::LANG_CREATE, $createEvent);

            if (false === $createEvent->hasLang()) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', 'Lang'))
                );
            }

            /** @var Lang $createdObject */
            $createdObject = $createEvent->getLang();
            $this->adminLogAppend(
                AdminResources::LANGUAGE,
                AccessManager::CREATE,
                sprintf(
                    "%s %s (ID %s) created",
                    'Lang',
                    $createdObject->getTitle(),
                    $createdObject->getId()
                ),
                $createdObject->getId()
            );

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj creation", array('%obj' => 'Lang')),
                $error_msg,
                $createForm,
                $ex
            );

            // At this point, the form has error, and should be redisplayed.
            $response = $this->renderDefault();
        }

        return $response;
    }

    public function deleteAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::DELETE)) {
            return $response;
        }

        $error_msg = false;

        try {
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get("_token")
            );

            $deleteEvent = new LangDeleteEvent($this->getRequest()->get('language_id', 0));

            $this->dispatch(TheliaEvents::LANG_DELETE, $deleteEvent);

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (\Exception $ex) {
            Tlog::getInstance()->error(sprintf("error during language removal with message : %s", $ex->getMessage()));
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $response = $this->renderDefault(array(
                'error_message' => $error_msg
            ));
        }

        return $response;
    }

    public function defaultBehaviorAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = false;
        $ex = null;

        $behaviorForm = $this->createForm(AdminForm::LANG_DEFAULT_BEHAVIOR);

        try {
            $form = $this->validateForm($behaviorForm);

            $event = new LangDefaultBehaviorEvent($form->get('behavior')->getData());

            $this->dispatch(TheliaEvents::LANG_DEFAULTBEHAVIOR, $event);

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj creation", array('%obj' => 'Lang')),
                $error_msg,
                $behaviorForm,
                $ex
            );

            // At this point, the form has error, and should be redisplayed.
            $response = $this->renderDefault();
        }

        return $response;
    }

    public function domainAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = false;
        $ex = null;
        $langUrlForm = $this->createForm(AdminForm::LANG_URL);

        try {
            $form = $this->validateForm($langUrlForm);

            $data = $form->getData();
            $event = new LangUrlEvent();
            foreach ($data as $key => $value) {
                if (false !== strpos($key, LangUrlForm::LANG_PREFIX)) {
                    $event->addUrl(substr($key, strlen(LangUrlForm::LANG_PREFIX)), $value);
                }
            }

            $this->dispatch(TheliaEvents::LANG_URL, $event);

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj creation", array('%obj' => 'Lang')),
                $error_msg,
                $langUrlForm,
                $ex
            );

            // At this point, the form has error, and should be redisplayed.
            $response = $this->renderDefault();
        }

        return $response;
    }

    public function activateDomainAction()
    {
        return $this->domainActivation(1);
    }

    public function deactivateDomainAction()
    {
        return $this->domainActivation(0);
    }

    private function domainActivation($activate)
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        ConfigQuery::create()
            ->filterByName('one_domain_foreach_lang')
            ->update(array('Value' => $activate));

        return $this->generateRedirectFromRoute('admin.configuration.languages');
    }

    /**
     * @param string $eventName
     * @param LangEvent $event
     * @return Response
     */
    protected function toggleLangDispatch($eventName, $event)
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $errorMessage = null;

        $this->getTokenProvider()->checkToken(
            $this->getRequest()->query->get('_token')
        );

        try {
            $this->dispatch($eventName, $event);

            if (false === $event->hasLang()) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', 'Lang'))
                );
            }

            $changedObject = $event->getLang();
            $this->adminLogAppend(
                AdminResources::LANGUAGE,
                AccessManager::UPDATE,
                sprintf(
                    "%s %s (ID %s) modified",
                    'Lang',
                    $changedObject->getTitle(),
                    $changedObject->getId()
                ),
                $changedObject->getId()
            );

        } catch (\Exception $e) {
            Tlog::getInstance()->error(sprintf("Error on changing languages with message : %s", $e->getMessage()));
            $errorMessage = $e->getMessage();
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            return new JsonResponse(
                $errorMessage !== null ? ['message' => $errorMessage] : [],
                $errorMessage !== null ? 500 : 200
            );
        }

        if ($errorMessage !== null) {
            return $this->renderDefault(['error_message' => $errorMessage], 500);
        } else {
            return $this->generateRedirectFromRoute('admin.configuration.languages');
        }
    }
}
