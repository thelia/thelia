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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Form\Tax\TaxType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormErrorRenderer;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormValidator;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminLogger;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\LangQuery;
use Thelia\Model\Tax;
use Thelia\Model\TaxQuery;
use Twig\Environment;

#[Route('/admin/configuration/taxes', name: 'admin.configuration.taxes.')]
final class TaxController
{
    private const RESOURCE = AdminResources::TAX;
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/tax/edit.html.twig';
    private const LIST_ROUTE = 'admin.configuration.taxes-rules.list';
    private const UPDATE_ROUTE = 'admin.configuration.taxes.update';
    private const CREATE_FORM_NAME = 'thelia_tax_creation';
    private const UPDATE_FORM_NAME = 'thelia_tax_modification';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly AdminFormValidator $validator,
        private readonly AdminFormErrorRenderer $errorRenderer,
        private readonly AdminLogger $adminLogger,
        private readonly EventDispatcherInterface $events,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/update/{tax_id}', name: 'update', requirements: ['tax_id' => '\d+'], methods: ['GET'])]
    public function update(int $tax_id, Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $tax = TaxQuery::create()->findPk($tax_id);
        if ($tax === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->editionLocale($request);
        $tax->setLocale($locale);

        $form = $this->createUpdateForm($this->taxToFormData($tax, $locale, $this->withRequirements($tax)));

        return $this->renderEdit($form, $tax);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::CREATE)) {
            return $denied;
        }

        $form = $this->formFactory->createNamed(self::CREATE_FORM_NAME, TaxType::class, null, [
            'csrf_protection' => false,
        ]);

        try {
            $validated = $this->validator->validate($form);
            $event = $this->buildEvent($validated);
            $this->events->dispatch($event, TheliaEvents::TAX_CREATE);

            $tax = $event->getTax();
            if ($tax !== null) {
                $this->adminLogger->log(
                    self::RESOURCE,
                    AccessManager::CREATE,
                    \sprintf('Tax %s (ID %d) created', (string) $tax->getTitle(), (int) $tax->getId()),
                    (int) $tax->getId(),
                );
            }

            return new RedirectResponse($this->urls->generate(self::UPDATE_ROUTE, [
                'tax_id' => $tax?->getId() ?? 0,
            ]));
        } catch (\Throwable $exception) {
            $this->errorRenderer->setup(
                $this->translator->trans('Tax creation'),
                $exception->getMessage(),
                $form,
                $exception,
            );

            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $taxId = (int) ($request->request->get('tax_id', $request->get('tax_id', 0)));
        $form = $this->createUpdateForm(null);

        try {
            $validated = $this->validator->validate($form);
            $event = $this->buildEvent($validated);
            $this->events->dispatch($event, TheliaEvents::TAX_UPDATE);

            $tax = $event->getTax();
            if ($tax !== null) {
                $this->adminLogger->log(
                    self::RESOURCE,
                    AccessManager::UPDATE,
                    \sprintf('Tax %s (ID %d) modified', (string) $tax->getTitle(), (int) $tax->getId()),
                    (int) $tax->getId(),
                );
            }

            return new RedirectResponse($this->urls->generate(self::UPDATE_ROUTE, [
                'tax_id' => $tax?->getId() ?? $taxId,
            ]));
        } catch (\Throwable $exception) {
            $this->errorRenderer->setup(
                $this->translator->trans('Tax update'),
                $exception->getMessage(),
                $form,
                $exception,
            );

            $tax = TaxQuery::create()->findPk($taxId);
            if ($tax === null) {
                return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
            }

            return $this->renderEdit($form, $tax, Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $event = new TaxEvent();
        $event->setId((int) $request->get('tax_id', 0));

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::TAX_DELETE,
            actionLabel: 'Tax deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function createUpdateForm(?array $data): FormInterface
    {
        return $this->formFactory->createNamed(self::UPDATE_FORM_NAME, TaxType::class, $data, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);
    }

    private function renderEdit(FormInterface $form, Tax $tax, int $status = Response::HTTP_OK): Response
    {
        return new Response(
            $this->twig->render(self::EDIT_TEMPLATE, [
                'form' => $form->createView(),
                'tax' => $tax,
            ]),
            $status,
        );
    }

    private function buildEvent(FormInterface $validated): TaxEvent
    {
        $data = $validated->getData() ?? [];
        $event = new TaxEvent();
        $event->setLocale((string) ($data['locale'] ?? ''));
        $event->setTitle((string) ($data['title'] ?? ''));
        $event->setDescription((string) ($data['description'] ?? ''));
        $event->setType((string) ($data['type'] ?? ''));
        $event->setRequirements($this->extractRequirements((string) ($data['type'] ?? ''), $data));

        if (isset($data['id']) && (int) $data['id'] > 0) {
            $event->setId((int) $data['id']);
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function extractRequirements(string $type, array $data): array
    {
        $requirements = [];
        foreach ($data as $key => $value) {
            if (!\is_string($key) || !str_contains($key, ':')) {
                continue;
            }

            [$fieldType, $name] = explode(':', $key, 2);
            if ($fieldType === $type) {
                $requirements[$name] = $value;
            }
        }

        return $requirements;
    }

    /**
     * @param array<string, mixed> $requirementValues
     *
     * @return array<string, mixed>
     */
    private function taxToFormData(Tax $tax, string $locale, array $requirementValues = []): array
    {
        return array_merge([
            'id' => $tax->getId(),
            'locale' => $locale,
            'title' => $tax->getTitle(),
            'description' => $tax->getDescription(),
            'type' => Tax::escapeTypeName((string) $tax->getType()),
        ], $requirementValues);
    }

    /**
     * @return array<string, mixed>
     */
    private function withRequirements(Tax $tax): array
    {
        $escaped = Tax::escapeTypeName((string) $tax->getType());

        $values = [];
        foreach ($tax->getRequirements() as $name => $value) {
            $values[$escaped.':'.$name] = $value;
        }

        return $values;
    }

    private function editionLocale(Request $request): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
