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

use BackOfficeDefaultTwigBundle\Form\Configuration\MessageType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Message\MessageCreateEvent;
use Thelia\Core\Event\Message\MessageDeleteEvent;
use Thelia\Core\Event\Message\MessageUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Twig\Environment;

#[Route('/admin/configuration/messages', name: 'admin.configuration.messages.')]
final class MessageController
{
    private const RESOURCE = AdminResources::MESSAGE;
    private const LIST_ROUTE = 'admin.configuration.messages.default';
    private const EDIT_ROUTE = 'admin.configuration.messages.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/message/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/message/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $rows = [];
        foreach (MessageQuery::create()->orderByName()->find() as $message) {
            \assert($message instanceof Message);
            $message->setLocale($locale);
            $rows[] = $this->messageToRow($message);
        }

        $createForm = $this->formFactory->createNamed('thelia_message_create', MessageType::class, [
            'locale' => $locale,
        ], ['csrf_protection' => false]);

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
        ]));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_message_create', MessageType::class, [
            'locale' => $this->defaultLocale(),
        ], ['csrf_protection' => false]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::MESSAGE_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Message creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update/{message_id}', name: 'update', methods: ['GET'], requirements: ['message_id' => '\d+'])]
    public function updateView(int $message_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $message = MessageQuery::create()->findPk($message_id);
        if ($message === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $message->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'message' => $message,
            'form' => $this->buildUpdateForm($message, $locale)->createView(),
            'preview_html_url' => $this->urls->generate('admin.email.preview_html', ['messageId' => $message_id]),
            'preview_text_url' => $this->urls->generate('admin.email.preview_text', ['messageId' => $message_id]),
            'send_test_url' => $this->urls->generate('admin.email.test_send', ['messageId' => $message_id]),
            'store_email' => (string) ConfigQuery::read('store_email', ''),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_message_update', MessageType::class, null, [
            'include_id' => true,
            'include_body' => true,
            'csrf_protection' => false,
        ]);

        $messageId = (int) $request->request->get('message_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::MESSAGE_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Message update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['message_id' => $messageId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['message_id' => $messageId])),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new MessageDeleteEvent((int) $request->get('message_id', 0)),
            eventName: TheliaEvents::MESSAGE_DELETE,
            actionLabel: 'Message deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function createEvent(FormInterface $validated): MessageCreateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new MessageCreateEvent();
        $event->setMessageName((string) ($data['message_name'] ?? ''))
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setSecured((bool) ($data['secured'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): MessageUpdateEvent
    {
        $data = $validated->getData() ?? [];
        $event = new MessageUpdateEvent((int) ($data['id'] ?? 0));
        $event->setMessageName((string) ($data['message_name'] ?? ''))
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setSecured((bool) ($data['secured'] ?? false))
            ->setSubject((string) ($data['subject'] ?? ''))
            ->setHtmlMessage((string) ($data['html_message'] ?? ''))
            ->setTextMessage((string) ($data['text_message'] ?? ''));

        return $event;
    }

    private function buildUpdateForm(Message $message, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_message_update', MessageType::class, [
            'id' => $message->getId(),
            'locale' => $locale,
            'message_name' => $message->getName(),
            'title' => $message->getTitle(),
            'secured' => (bool) $message->getSecured(),
            'subject' => $message->getSubject(),
            'html_message' => $message->getHtmlMessage(),
            'text_message' => $message->getTextMessage(),
        ], [
            'include_id' => true,
            'include_body' => true,
            'csrf_protection' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function messageToRow(Message $message): array
    {
        $id = (int) $message->getId();
        $actions = [
            new RowAction(kind: 'edit', label: $this->translator->trans('Edit'), href: $this->urls->generate(self::EDIT_ROUTE, ['message_id' => $id]), grantedAttribute: AccessManager::UPDATE, grantedSubject: self::RESOURCE),
            new RowAction(kind: 'delete', label: $this->translator->trans('Delete'), modalTarget: '#message-delete-modal', grantedAttribute: AccessManager::DELETE, grantedSubject: self::RESOURCE, dataAttributes: ['message-id' => $id, 'message-label' => (string) $message->getName()]),
        ];

        return [
            'id' => $id,
            'name' => (string) $message->getName(),
            'title' => (string) $message->getTitle(),
            'secured' => (bool) $message->getSecured(),
            'secured_label' => $message->getSecured() ? $this->translator->trans('System') : $this->translator->trans('User'),
            '_actions' => $actions,
        ];
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
