<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
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
 * Class LangController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangController extends BaseAdminController
{
    public function defaultAction()
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->renderDefault();
    }

    public function renderDefault(array $param = [], int $status = 200): Response
    {
        $data = [];

        foreach (LangQuery::create()->find() as $lang) {
            $data[LangUrlForm::LANG_PREFIX.$lang->getId()] = $lang->getUrl();
        }

        $langUrlForm = $this->createForm(AdminForm::LANG_URL, FormType::class, $data);
        $this->getParserContext()->addForm($langUrlForm);

        return $this->render('languages', array_merge($param, [
            'lang_without_translation' => ConfigQuery::getDefaultLangWhenNoTranslationAvailable(),
            'one_domain_per_lang' => ConfigQuery::isMultiDomainActivated(),
        ]), $status);
    }

    public function updateAction($lang_id)
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $this->checkXmlHttpRequest();

        $lang = LangQuery::create()->findPk($lang_id);

        $langForm = $this->createForm(AdminForm::LANG_UPDATE, FormType::class, [
            'id' => $lang->getId(),
            'title' => $lang->getTitle(),
            'code' => $lang->getCode(),
            'locale' => $lang->getLocale(),
            'date_time_format' => $lang->getDateTimeFormat(),
            'date_format' => $lang->getDateFormat(),
            'time_format' => $lang->getTimeFormat(),
            'decimal_separator' => $lang->getDecimalSeparator(),
            'thousands_separator' => $lang->getThousandsSeparator(),
            'decimals' => $lang->getDecimals(),
        ]);

        $this->getParserContext()->addForm($langForm);

        return $this->render('ajax/language-update-modal', [
            'lang_id' => $lang_id,
        ]);
    }

    public function processUpdateAction(EventDispatcherInterface $eventDispatcher, $lang_id): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $error_msg = false;

        $langForm = $this->createForm(AdminForm::LANG_UPDATE);

        try {
            $form = $this->validateForm($langForm);

            $event = new LangUpdateEvent($form->get('id')->getData());
            $event = $this->hydrateEvent($event, $form);

            $eventDispatcher->dispatch($event, TheliaEvents::LANG_UPDATE);

            if (false === $event->hasLang()) {
                throw new \LogicException($this->getTranslator()->trans('No %obj was updated.', ['%obj', 'Lang']));
            }

            /** @var Lang $changedObject */
            $changedObject = $event->getLang();
            $this->adminLogAppend(
                AdminResources::LANGUAGE,
                AccessManager::UPDATE,
                \sprintf(
                    '%s %s (ID %s) modified',
                    'Lang',
                    $changedObject->getTitle(),
                    $changedObject->getId(),
                ),
                $changedObject->getId(),
            );

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (\Exception $exception) {
            $error_msg = $this->getTranslator()->trans('Failed to update language definition: %ex', ['%ex' => $exception->getMessage()]);
            Tlog::getInstance()->addError('Failed to update language definition', $exception->getMessage());
        }

        if (false !== $error_msg) {
            $response = $this->renderDefault(['error_message' => $error_msg]);
        }

        return $response;
    }

    protected function hydrateEvent(LangCreateEvent $event, Form $form): LangCreateEvent
    {
        return $event
            ->setTitle($form->get('title')->getData())
            ->setCode($form->get('code')->getData())
            ->setLocale($form->get('locale')->getData())
            ->setDateTimeFormat($form->get('date_time_format')->getData())
            ->setDateFormat($form->get('date_format')->getData())
            ->setTimeFormat($form->get('time_format')->getData())
            ->setDecimalSeparator($form->get('decimal_separator')->getData())
            ->setThousandsSeparator($form->get('thousands_separator')->getData())
            ->setDecimals($form->get('decimals')->getData());
    }

    public function toggleDefaultAction(EventDispatcherInterface $eventDispatcher, $lang_id): Response|JsonResponse|RedirectResponse
    {
        return $this->toggleLangDispatch(
            $eventDispatcher,
            TheliaEvents::LANG_TOGGLEDEFAULT,
            new LangToggleDefaultEvent($lang_id),
        );
    }

    public function toggleActiveAction(EventDispatcherInterface $eventDispatcher, $lang_id): Response|JsonResponse|RedirectResponse
    {
        return $this->toggleLangDispatch(
            $eventDispatcher,
            TheliaEvents::LANG_TOGGLEACTIVE,
            new LangToggleActiveEvent($lang_id),
        );
    }

    public function toggleVisibleAction(EventDispatcherInterface $eventDispatcher, $lang_id): Response|JsonResponse|RedirectResponse
    {
        return $this->toggleLangDispatch(
            $eventDispatcher,
            TheliaEvents::LANG_TOGGLEVISIBLE,
            new LangToggleVisibleEvent($lang_id),
        );
    }

    public function addAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::CREATE)) instanceof Response) {
            return $response;
        }

        $createForm = $this->createForm(AdminForm::LANG_CREATE);

        $error_msg = false;
        $ex = null;

        try {
            $form = $this->validateForm($createForm);

            $createEvent = new LangCreateEvent();
            $createEvent = $this->hydrateEvent($createEvent, $form);

            $eventDispatcher->dispatch($createEvent, TheliaEvents::LANG_CREATE);

            if (false === $createEvent->hasLang()) {
                throw new \LogicException($this->getTranslator()->trans('No %obj was updated.', ['%obj', 'Lang']));
            }

            /** @var Lang $createdObject */
            $createdObject = $createEvent->getLang();
            $this->adminLogAppend(
                AdminResources::LANGUAGE,
                AccessManager::CREATE,
                \sprintf(
                    '%s %s (ID %s) created',
                    'Lang',
                    $createdObject->getTitle(),
                    $createdObject->getId(),
                ),
                $createdObject->getId(),
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
                $this->getTranslator()->trans('%obj creation', ['%obj' => 'Lang']),
                $error_msg,
                $createForm,
                $ex,
            );

            // At this point, the form has error, and should be redisplayed.
            $response = $this->renderDefault();
        }

        return $response;
    }

    public function deleteAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::DELETE)) instanceof Response) {
            return $response;
        }

        $error_msg = false;

        try {
            $this->getTokenProvider()->checkToken(
                $this->getRequest()->query->get('_token'),
            );

            $deleteEvent = new LangDeleteEvent($this->getRequest()->get('language_id', 0));

            $eventDispatcher->dispatch($deleteEvent, TheliaEvents::LANG_DELETE);

            $response = $this->generateRedirectFromRoute('admin.configuration.languages');
        } catch (\Exception $exception) {
            Tlog::getInstance()->error(\sprintf('error during language removal with message : %s', $exception->getMessage()));
            $error_msg = $exception->getMessage();
        }

        if (false !== $error_msg) {
            $response = $this->renderDefault([
                'error_message' => $error_msg,
            ]);
        }

        return $response;
    }

    public function defaultBehaviorAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $error_msg = false;
        $ex = null;

        $behaviorForm = $this->createForm(AdminForm::LANG_DEFAULT_BEHAVIOR);

        try {
            $form = $this->validateForm($behaviorForm);

            $event = new LangDefaultBehaviorEvent($form->get('behavior')->getData());

            $eventDispatcher->dispatch($event, TheliaEvents::LANG_DEFAULTBEHAVIOR);

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
                $this->getTranslator()->trans('%obj creation', ['%obj' => 'Lang']),
                $error_msg,
                $behaviorForm,
                $ex,
            );

            // At this point, the form has error, and should be redisplayed.
            $response = $this->renderDefault();
        }

        return $response;
    }

    public function domainAction(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
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
                if (str_contains((string) $key, LangUrlForm::LANG_PREFIX)) {
                    $event->addUrl(substr((string) $key, \strlen(LangUrlForm::LANG_PREFIX)), $value);
                }
            }

            $eventDispatcher->dispatch($event, TheliaEvents::LANG_URL);

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
                $this->getTranslator()->trans('%obj creation', ['%obj' => 'Lang']),
                $error_msg,
                $langUrlForm,
                $ex,
            );

            // At this point, the form has error, and should be redisplayed.
            $response = $this->renderDefault();
        }

        return $response;
    }

    public function activateDomainAction(): Response|RedirectResponse
    {
        return $this->domainActivation(1);
    }

    public function deactivateDomainAction(): Response|RedirectResponse
    {
        return $this->domainActivation(0);
    }

    private function domainActivation(int $activate): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        ConfigQuery::create()
            ->filterByName('one_domain_foreach_lang')
            ->update(['Value' => $activate], null, true);

        return $this->generateRedirectFromRoute('admin.configuration.languages');
    }

    protected function toggleLangDispatch(EventDispatcherInterface $eventDispatcher, ?string $eventName, LangEvent $event): Response|JsonResponse|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $errorMessage = null;

        $this->getTokenProvider()->checkToken(
            $this->getRequest()->query->get('_token'),
        );

        try {
            $eventDispatcher->dispatch($event, $eventName);

            if (false === $event->hasLang()) {
                throw new \LogicException($this->getTranslator()->trans('No %obj was updated.', ['%obj', 'Lang']));
            }

            $changedObject = $event->getLang();
            $this->adminLogAppend(
                AdminResources::LANGUAGE,
                AccessManager::UPDATE,
                \sprintf(
                    '%s %s (ID %s) modified',
                    'Lang',
                    $changedObject->getTitle(),
                    $changedObject->getId(),
                ),
                $changedObject->getId(),
            );
        } catch (\Exception $exception) {
            Tlog::getInstance()->error(\sprintf('Error on changing languages with message : %s', $exception->getMessage()));
            $errorMessage = $exception->getMessage();
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            return new JsonResponse(
                null !== $errorMessage ? ['message' => $errorMessage] : [],
                null !== $errorMessage ? 500 : 200,
            );
        }

        if (null !== $errorMessage) {
            return $this->renderDefault(['error_message' => $errorMessage], 500);
        }

        return $this->generateRedirectFromRoute('admin.configuration.languages');
    }
}
