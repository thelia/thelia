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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Config\ConfigCreateEvent;
use Thelia\Core\Event\Config\ConfigDeleteEvent;
use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;
use Thelia\Service\ConfigCacheService;

/**
 * Manages variables.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'variable',
            'name',
            'order',
            AdminResources::CONFIG,
            TheliaEvents::CONFIG_CREATE,
            TheliaEvents::CONFIG_UPDATE,
            TheliaEvents::CONFIG_DELETE // no position change
        );
    }

    /**
     * The default action is displaying the list.
     *
     * @return Response the response
     */
    public function defaultAction(?ConfigCacheService $configCacheService = null): Response
    {
        // Force reinit config cache
        if ($configCacheService instanceof ConfigCacheService) {
            $configCacheService->initCacheConfigs(true);
        }

        return parent::defaultAction();
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::CONFIG_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::CONFIG_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $createEvent = new ConfigCreateEvent();

        $createEvent
            ->setEventName($formData['name'])
            ->setValue($formData['value'])
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setHidden($formData['hidden'])
            ->setSecured($formData['secured'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $changeEvent = new ConfigUpdateEvent($formData['id']);

        $changeEvent
            ->setEventName($formData['name'])
            ->setValue($formData['value'])
            ->setHidden($formData['hidden'])
            ->setSecured($formData['secured'])
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
        ;

        return $changeEvent;
    }

    protected function getDeleteEvent(): ConfigDeleteEvent
    {
        return new ConfigDeleteEvent($this->getRequest()->get('variable_id'));
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasConfig();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        // Prepare the data that will hydrate the form
        $data = [
            'id' => $object->getId(),
            'name' => $object->getName(),
            'value' => $object->getValue(),
            'hidden' => $object->getHidden(),
            'secured' => $object->getSecured(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::CONFIG_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasConfig() ? $event->getConfig() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $config = ConfigQuery::create()
        ->findOneById($this->getRequest()->get('variable_id'));

        if (null !== $config) {
            $config->setLocale($this->getCurrentEditionLocale());
        }

        return $config;
    }

    /**
     * @param Config $object
     */
    protected function getObjectLabel(ActiveRecordInterface $object): ?string
    {
        return $object->getName();
    }

    /**
     * @param Config $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function renderListTemplate($currentOrder): Response
    {
        return $this->render('variables', ['order' => $currentOrder]);
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render('variable-edit', ['variable_id' => $this->getRequest()->get('variable_id')]);
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.variables.update',
            ['variable_id' => $this->getRequest()->get('variable_id')]
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.variables.default');
    }

    /**
     * Change values modified directly from the variable list.
     *
     * @return Response the response
     */
    public function changeValuesAction(EventDispatcherInterface $dispatcher): Response|RedirectResponse
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $variables = $this->getRequest()->get('variable', []);

        // Process all changed variables
        foreach ($variables as $id => $value) {
            $event = new ConfigUpdateEvent($id);
            $event->setValue($value);

            $dispatcher->dispatch($event, TheliaEvents::CONFIG_SETVALUE);
        }

        return $this->generateRedirectFromRoute('admin.configuration.variables.default');
    }
}
