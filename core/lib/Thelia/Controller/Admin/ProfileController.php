<?php /** @noinspection PhpRedundantOptionalArgumentInspection */
/** @noinspection PhpRedundantOptionalArgumentInspection */

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


use Exception;
use LogicException;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ProfileUpdateModuleAccessForm;
use Thelia\Form\ProfileUpdateResourceAccessForm;
use Thelia\Model\Profile;
use Thelia\Model\ProfileQuery;

class ProfileController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'profile',
            'manual',
            'order',
            AdminResources::PROFILE,
            TheliaEvents::PROFILE_CREATE,
            TheliaEvents::PROFILE_UPDATE,
            TheliaEvents::PROFILE_DELETE
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::PROFILE_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::PROFILE_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
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

    protected function getUpdateEvent(array $formData): ActionEvent
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

    protected function getDeleteEvent(): ProfileEvent
    {
        $event = new ProfileEvent();

        $event->setId(
            $this->getRequest()->get('profile_id', 0)
        );

        return $event;
    }

    /**
     * @param ProfileEvent $event
     */
    protected function eventContainsObject($event): bool
    {
        return $event->hasProfile();
    }

    /**
     * @param Profile $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'description' => $object->getDescription(),
            'code' => $object->getCode(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::PROFILE_MODIFICATION, FormType::class, $data);
    }

    /**
     * @param Profile $object
     */
    protected function hydrateResourceUpdateForm($object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::PROFILE_UPDATE_RESOURCE_ACCESS, FormType::class, $data);
    }

    /**
     * @param Profile $object
     */
    protected function hydrateModuleUpdateForm($object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::PROFILE_UPDATE_MODULE_ACCESS, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasProfile() ? $event->getProfile() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $profile = ProfileQuery::create()
            ->findOneById($this->getRequest()->get('profile_id', 0));

        if (null !== $profile) {
            $profile->setLocale($this->getCurrentEditionLocale());
        }

        return $profile;
    }

    protected function getObjectLabel(?ActiveRecordInterface $object): string
    {
        if ($object instanceof Profile) {
            return $object->getTitle();
        }

        return (string) $object;
    }

    protected function getObjectId(ActiveRecordInterface $object): int
    {
        if ($object instanceof Profile) {
            return (string) $object->getId();
        }

        return (string) $object;
    }

    protected function getViewArguments(): array
    {
        return (null !== $tab = $this->getRequest()->get('tab')) ? ['tab' => $tab] : [];
    }

    protected function getRouteArguments($profile_id = null): array
    {
        return [
            'profile_id' => $profile_id ?? $this->getRequest()->get('profile_id'),
        ];
    }

    protected function renderListTemplate($currentOrder): Response
    {
        // We always return to the feature edition form
        return $this->render(
            'profiles'
        );
    }

    protected function renderEditionTemplate(): Response
    {
        // We always return to the feature edition form
        return $this->render('profile-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        // We always return to the feature edition form
        return $this->generateRedirectFromRoute(
            'admin.configuration.profiles.update',
            $this->getViewArguments(),
            $this->getRouteArguments()
        );
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param ActionEvent $createEvent the create event
     *
     * @return Response
     */
    protected function performAdditionalCreateAction(ActionEvent $createEvent): ?\Symfony\Component\HttpFoundation\Response
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.profiles.update',
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getProfile()->getId())
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.profiles.list');
    }

    public function updateAction(ParserContext $parserContext): \Symfony\Component\HttpFoundation\Response
    {
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $object = $this->getExistingObject();

        if ($object != null) {
            // Hydrate the form and pass it to the parser
            $resourceAccessForm = $this->hydrateResourceUpdateForm($object);
            $moduleAccessForm = $this->hydrateModuleUpdateForm($object);

            // Pass it to the parser
            $parserContext->addForm($resourceAccessForm);
            $parserContext->addForm($moduleAccessForm);
        }

        return parent::updateAction($parserContext);
    }

    protected function getUpdateResourceAccessEvent(array $formData): ProfileEvent
    {
        $event = new ProfileEvent();

        $event->setId($formData['id']);
        $event->setResourceAccess($this->getResourceAccess($formData));

        return $event;
    }

    protected function getUpdateModuleAccessEvent(array $formData): ProfileEvent
    {
        $event = new ProfileEvent();

        $event->setId($formData['id']);
        $event->setModuleAccess($this->getModuleAccess($formData));

        return $event;
    }

    /**
     * @return mixed[]
     */
    protected function getResourceAccess($formData): array
    {
        $requirements = [];
        foreach ($formData as $data => $value) {
            if (!strstr((string) $data, ':')) {
                continue;
            }

            $explosion = explode(':', (string) $data);

            $prefix = array_shift($explosion);

            if ($prefix != ProfileUpdateResourceAccessForm::RESOURCE_ACCESS_FIELD_PREFIX) {
                continue;
            }

            $requirements[implode('.', $explosion)] = $value;
        }

        return $requirements;
    }

    /**
     * @return mixed[]
     */
    protected function getModuleAccess($formData): array
    {
        $requirements = [];
        foreach ($formData as $data => $value) {
            if (!strstr((string) $data, ':')) {
                continue;
            }

            $explosion = explode(':', (string) $data);

            $prefix = array_shift($explosion);

            if ($prefix != ProfileUpdateModuleAccessForm::MODULE_ACCESS_FIELD_PREFIX) {
                continue;
            }

            $requirements[implode('.', $explosion)] = $value;
        }

        return $requirements;
    }

    public function processUpdateResourceAccess(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        // Create the form from the request
        $changeForm = $this->createForm(AdminForm::PROFILE_UPDATE_RESOURCE_ACCESS);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateResourceAccessEvent($data);

            $eventDispatcher->dispatch($changeEvent, TheliaEvents::PROFILE_RESOURCE_ACCESS_UPDATE);

            if (!$this->eventContainsObject($changeEvent)) {
                throw new LogicException(
                    $this->getTranslator()->trans('No %obj was updated.', ['%obj', $this->objectName])
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        '%s %s (ID %s) modified',
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            return $this->redirectToEditionTemplate();
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext($this->getTranslator()->trans('%obj modification', ['%obj' => 'taxrule']), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    public function processUpdateModuleAccess(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        // Create the form from the request
        $changeForm = $this->createForm(AdminForm::PROFILE_UPDATE_MODULE_ACCESS);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateModuleAccessEvent($data);

            $eventDispatcher->dispatch($changeEvent, TheliaEvents::PROFILE_MODULE_ACCESS_UPDATE);

            if (!$this->eventContainsObject($changeEvent)) {
                throw new LogicException(
                    $this->getTranslator()->trans('No %obj was updated.', ['%obj', $this->objectName])
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        '%s %s (ID %s) modified',
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            return $this->redirectToEditionTemplate();
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext($this->getTranslator()->trans('%obj modification', ['%obj' => 'taxrule']), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }
}
