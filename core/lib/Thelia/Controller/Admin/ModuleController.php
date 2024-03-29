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

namespace Thelia\Controller\Admin;

use Michelf\MarkdownExtra;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\InvalidModuleException;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ModuleInstallForm;
use Thelia\Log\Tlog;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\ModuleManagement;
use Thelia\Tools\TokenProvider;

/**
 * Class ModuleController.
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 */
class ModuleController extends AbstractCrudController
{
    protected $moduleErrors = [];

    public function __construct()
    {
        parent::__construct(
            'module',
            'manual',
            'module_order',
            AdminResources::MODULE,
            null,
            TheliaEvents::MODULE_UPDATE,
            null,
            null,
            TheliaEvents::MODULE_UPDATE_POSITION
        );
    }

    protected function getCreationForm()
    {
        return null;
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::MODULE_MODIFICATION);
    }

    protected function getCreationEvent($formData)
    {
        return null;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new ModuleEvent();

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
        return null;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('module_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    protected function eventContainsObject($event)
    {
        return $event->hasModule();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, $object)
    {
        $object->setLocale($this->getCurrentEditionLocale());
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::MODULE_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasModule() ? $event->getModule() : null;
    }

    protected function getExistingObject()
    {
        $module = ModuleQuery::create()
            ->findOneById($this->getRequest()->get('module_id', 0));

        if (null !== $module) {
            $module->setLocale($this->getCurrentEditionLocale());
        }

        return $module;
    }

    /**
     * @param Module $object
     *
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param Module $object
     *
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments()
    {
        return [];
    }

    protected function getRouteArguments($module_id = null)
    {
        $request = $this->getRequest();

        return [
            'module_id' => $module_id === null ? $request->get('module_id') : $module_id,
            'current_tab' => $request->get('current_tab', 'general'),
        ];
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'modules',
            [
                'module_order' => $currentOrder,
                'module_errors' => $this->moduleErrors,
            ]
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('module-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null)
    {
        return $this->generateRedirectFromRoute(
            'admin.module.update',
            $this->getViewArguments(),
            $this->getRouteArguments()
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.module');
    }

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::VIEW)) {
            return $response;
        }

        try {
            $moduleManagement = new ModuleManagement($this->getContainer());
            $moduleManagement->updateModules($this->getContainer());
        } catch (InvalidModuleException $ex) {
            $this->moduleErrors = $ex->getErrors();
        } catch (Exception $ex) {
            Tlog::getInstance()->addError('Failed to get modules list:', $ex);
        }

        return $this->renderList();
    }

    public function configureAction($module_code)
    {
        $module = ModuleQuery::create()->findOneByCode($module_code);

        if (null === $module) {
            throw new \InvalidArgumentException(sprintf('Module `%s` does not exists', $module_code));
        }

        if (null !== $response = $this->checkAuth([], $module_code, AccessManager::VIEW)) {
            return $response;
        }

        return $this->render(
            'module-configure',
            [
                'module_code' => $module_code,
            ]
        );
    }

    public function toggleActivationAction(EventDispatcherInterface $eventDispatcher, $module_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::UPDATE)) {
            return $response;
        }
        $message = null;
        try {
            $event = new ModuleToggleActivationEvent($module_id);
            $eventDispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);

            if (null === $event->getModule()) {
                throw new \LogicException(
                    $this->getTranslator()->trans('No %obj was updated.', ['%obj' => 'Module'])
                );
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();

            Tlog::getInstance()->addError('Failed to activate/deactivate module:', $e);
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($message) {
                $response = $this->jsonResponse(json_encode([
                    'error' => $message,
                ]), 500);
            } else {
                $response = $this->nullResponse();
            }
        } else {
            $response = $this->generateRedirectFromRoute('admin.module');
        }

        return $response;
    }

    public function deleteAction(
        Request $request,
        TokenProvider $tokenProvider,
        EventDispatcherInterface $eventDispatcher,
        ParserContext $parserContext
    ) {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::DELETE)) {
            return $response;
        }

        $message = false;

        try {
            $tokenProvider->checkToken(
                $request->query->get('_token')
            );

            $module_id = $request->get('module_id');

            $deleteEvent = new ModuleDeleteEvent($module_id);

            $deleteEvent->setDeleteData('1' == $request->get('delete-module-data', '0'));

            $eventDispatcher->dispatch($deleteEvent, TheliaEvents::MODULE_DELETE);

            if ($deleteEvent->hasModule() === false) {
                throw new \LogicException(
                    Translator::getInstance()->trans('No %obj was updated.', ['%obj' => 'Module'])
                );
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();

            Tlog::getInstance()->addError('Error during module removal', $e);
        }

        if (false !== $message) {
            $response = $this->render('modules', [
                'error_message' => $message,
            ]);
        } else {
            $response = $this->generateRedirectFromRoute('admin.module');
        }

        return $response;
    }

    public function installAction(EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::CREATE)) {
            return $response;
        }

        $newModule = null;
        $moduleDefinition = null;

        /** @var ModuleInstallForm $moduleInstall */
        $moduleInstall = $this->createForm(AdminForm::MODULE_INSTALL);

        try {
            $this->validateForm($moduleInstall, 'post');

            $moduleDefinition = $moduleInstall->getModuleDefinition();
            $modulePath = $moduleInstall->getModulePath();

            $moduleInstallEvent = new ModuleInstallEvent();
            $moduleInstallEvent
                ->setModulePath($modulePath)
                ->setModuleDefinition($moduleDefinition);

            $eventDispatcher->dispatch($moduleInstallEvent, TheliaEvents::MODULE_INSTALL);

            $newModule = $moduleInstallEvent->getModule();

            if (null !== $newModule) {
                $this->getSession()->getFlashBag()->add(
                    'module-installed',
                    $this->getTranslator()->trans(
                        'The module %module has been installed successfully.',
                        ['%module' => $moduleDefinition->getCode()]
                    )
                );

                return $this->generateRedirectFromRoute('admin.module');
            }
            $message = $this->getTranslator()->trans(
                'Sorry, an error occured.'
            );
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans('Sorry, an error occured: %s', ['%s' => $e->getMessage()]);
        }

        Tlog::getInstance()->error(sprintf('Error during module installation process. Exception was %s', $message));

        $moduleInstall->setErrorMessage($message);

        $this->getParserContext()
            ->addForm($moduleInstall)
            ->setGeneralError($message);

        return $this->render('modules');
    }

    public function informationAction($module_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::VIEW)) {
            return $response;
        }

        $status = 200;

        if (null !== $module = ModuleQuery::create()->findPk($module_id)) {
            $title = $module->setLocale($this->getSession()->getLang()->getLocale())->getTitle();

            // Get the module descriptor
            $moduleDescriptor = $module->getAbsoluteConfigPath().DS.'module.xml';

            if (false !== $xmlData = @simplexml_load_file($moduleDescriptor)) {
                // Transform the pseudo-array into a real array
                $arrayData = json_decode(json_encode((array) $xmlData), 1);

                $content = $this->renderRaw('ajax/module-information', [
                    'moduleId' => $module_id,
                    'moduleData' => $arrayData,
                ]);
            } else {
                $status = 500;

                $content = $this->getTranslator()->trans(
                    'Failed to load descriptor (module.xml) for module ID "%id".',
                    ['%id' => $module_id]
                );
            }
        } else {
            $status = 404;

            $title = $this->getTranslator()->trans('Error occured.');
            $content = $this->getTranslator()->trans('Module ID "%id" was not found.', ['%id' => $module_id]);
        }

        return new JsonResponse(['title' => $title, 'body' => $content], $status);
    }

    public function documentationAction($module_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [], AccessManager::VIEW)) {
            return $response;
        }

        $status = 200;

        $content = null;

        if (null !== $module = ModuleQuery::create()->findPk($module_id)) {
            $title = $module->setLocale($this->getSession()->getLang()->getLocale())->getTitle();

            $finder = null;

            // Check if the module has declared a documentation
            $moduleDescriptor = $module->getAbsoluteConfigPath().DS.'module.xml';

            if (false !== $xmlData = @simplexml_load_file($moduleDescriptor)) {
                $documentationDirectory = (string) $xmlData->documentation;

                if (!empty($documentationDirectory)) {
                    $finder = Finder::create()->files()->in($module->getAbsoluteBaseDir())->name('/.+\.md$/i');
                }
            }

            // Fallback to readme.md (if any)
            if ($finder == null || $finder->count() == 0) {
                $finder = Finder::create()->files()->in($module->getAbsoluteBaseDir())->name('/readme\.md/i');
            }

            // Merge all MD files
            if ($finder && $finder->count() > 0) {
                $finder->sortByName();

                /** @var \DirectoryIterator $file */
                foreach ($finder as $file) {
                    if (false !== $mdDocumentation = @file_get_contents($file->getPathname())) {
                        if ($content == null) {
                            $content = '';
                        }
                        $content .= MarkdownExtra::defaultTransform($mdDocumentation);
                    }
                }
            }
        } else {
            $status = 404;

            $title = $this->getTranslator()->trans('Error occured.');
            $content = $this->getTranslator()->trans('Module ID "%id" was not found.', ['%id' => $module_id]);
        }

        if ($content === null) {
            $content = $this->getTranslator()->trans('This module has no Markdown documentation.');
        }

        return new JsonResponse(['title' => $title, 'body' => $content], $status);
    }
}
