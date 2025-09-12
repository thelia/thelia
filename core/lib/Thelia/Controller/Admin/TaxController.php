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
use Propel\Runtime\Event\ActiveRecordEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Tax;
use Thelia\Model\TaxQuery;

class TaxController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'tax',
            'manual',
            'order',
            AdminResources::TAX,
            TheliaEvents::TAX_CREATE,
            TheliaEvents::TAX_UPDATE,
            TheliaEvents::TAX_DELETE,
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::TAX_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::TAX_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = new TaxEvent();

        $event->setLocale($formData['locale']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);
        $event->setType(Tax::unescapeTypeName($formData['type']));
        $event->setRequirements($this->getRequirements($formData['type'], $formData));

        return $event;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = new TaxEvent();

        $event->setLocale($formData['locale']);
        $event->setId($formData['id']);
        $event->setTitle($formData['title']);
        $event->setDescription($formData['description']);
        $event->setType(Tax::unescapeTypeName($formData['type']));
        $event->setRequirements($this->getRequirements($formData['type'], $formData));

        return $event;
    }

    protected function getDeleteEvent(): TaxEvent
    {
        $event = new TaxEvent();

        $event->setId(
            $this->getRequest()->get('tax_id', 0),
        );

        return $event;
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasTax();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'description' => $object->getDescription(),
            'type' => Tax::escapeTypeName($object->getType()),
        ];

        // Setup the object form
        return $this->createForm(
            AdminForm::TAX_MODIFICATION,
            FormType::class,
            $data,
        );
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasTax() ? $event->getTax() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $tax = TaxQuery::create()
            ->findOneById($this->getRequest()->get('tax_id', 0));

        if (null !== $tax) {
            $tax->setLocale($this->getCurrentEditionLocale());
        }

        return $tax;
    }

    /**
     * @param Tax $object
     */
    protected function getObjectLabel(ActiveRecordInterface $object): ?string
    {
        return $object->getTitle();
    }

    /**
     * @param Tax $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function getViewArguments(): array
    {
        return [];
    }

    protected function getRouteArguments($tax_id = null): array
    {
        return [
            'tax_id' => $tax_id ?? $this->getRequest()->get('tax_id'),
        ];
    }

    protected function renderListTemplate(string $currentOrder): Response
    {
        return $this->render(
            'taxes-rules',
        );
    }

    protected function renderEditionTemplate(): Response
    {
        // We always return to the feature edition form
        return $this->render('tax-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.taxes.update',
            $this->getViewArguments(),
            $this->getRouteArguments(),
        );
    }

    /**
     * Put in this method post object creation processing if required.
     */
    protected function performAdditionalCreateAction(ActionEvent|ActiveRecordEvent|null $createEvent): ?Response
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.taxes.update',
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getTax()->getId()),
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.taxes-rules.list');
    }

    /**
     * @return mixed[]
     */
    protected function getRequirements($type, $formData): array
    {
        $requirements = [];

        foreach ($formData as $data => $value) {
            if (!strstr((string) $data, ':')) {
                continue;
            }

            $couple = explode(':', (string) $data);

            if (2 === \count($couple) && $couple[0] === $type) {
                $requirements[$couple[1]] = $value;
            }
        }

        return $requirements;
    }
}
