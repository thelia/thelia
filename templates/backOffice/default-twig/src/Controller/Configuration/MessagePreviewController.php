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

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\MessageQuery;

/**
 * Renders the HTML/text preview for a mailing template and sends sample emails.
 *
 * Routes live under /admin/message/* to mirror the legacy admin.email.* names
 * exposed in Smarty (preview-button, send-test-mail-form) so module hooks and
 * legacy bookmarks keep resolving without modification.
 */
final class MessagePreviewController
{
    private const RESOURCE = AdminResources::MESSAGE;

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly TranslatorInterface $translator,
        private readonly ParserResolver $parserResolver,
        private readonly TemplateHelperInterface $templateHelper,
        private readonly MailerFactory $mailer,
    ) {
    }

    #[Route(path: '/admin/message/preview/{messageId}', name: 'admin.email.preview_html', methods: ['GET'], requirements: ['messageId' => '\d+'])]
    public function previewHtml(Request $request, int $messageId): Response
    {
        return $this->renderPreview($request, $messageId, true);
    }

    #[Route(path: '/admin/message/preview/text/{messageId}', name: 'admin.email.preview_text', methods: ['GET'], requirements: ['messageId' => '\d+'])]
    public function previewText(Request $request, int $messageId): Response
    {
        $response = $this->renderPreview($request, $messageId, false);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response;
    }

    #[Route(path: '/admin/message/send/{messageId}', name: 'admin.email.test_send', methods: ['POST'], requirements: ['messageId' => '\d+'])]
    public function sendSample(Request $request, int $messageId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $message = MessageQuery::create()->findPk($messageId);
        if ($message === null) {
            return new Response($this->translator->trans('Message not found.'), Response::HTTP_NOT_FOUND);
        }

        $recipient = trim((string) $request->request->get('recipient_email', ''));
        if ($recipient === '') {
            return new Response($this->translator->trans('Recipient email is required.'), Response::HTTP_BAD_REQUEST);
        }

        $parameters = $request->request->all();
        unset($parameters['recipient_email']);

        try {
            $this->mailer->sendEmailMessage(
                $message->getName(),
                [(string) ConfigQuery::read('store_email', '') => (string) ConfigQuery::read('store_name', 'Thelia')],
                [$recipient => $recipient],
                $parameters,
                $this->defaultLocale(),
            );

            return new Response($this->translator->trans('The message has been successfully sent to %recipient.', ['%recipient' => $recipient]));
        } catch (\Throwable $exception) {
            return new Response($this->translator->trans('Something goes wrong, the message was not sent to recipient. Error is : %err', ['%err' => $exception->getMessage()]));
        }
    }

    private function renderPreview(Request $request, int $messageId, bool $asHtml): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $message = MessageQuery::create()->findPk($messageId);
        if ($message === null) {
            return new Response($this->translator->trans('Message not found.'), Response::HTTP_NOT_FOUND);
        }

        $mailTemplate = $this->templateHelper->getActiveMailTemplate();

        try {
            $parser = $this->parserResolver->getParser($mailTemplate->getAbsolutePath(), null);
            $parser->setTemplateDefinition($mailTemplate, true);

            foreach ($request->query->all() as $key => $value) {
                $parser->assign($key, $value);
            }

            $message->setLocale($this->defaultLocale());
            $content = $asHtml ? $message->getHtmlMessageBody($parser) : $message->getTextMessageBody($parser);
        } catch (\Throwable $exception) {
            return new Response($this->translator->trans("You probably didn't inject the missing variable to preview the message. Error is : %err", ['%err' => $exception->getMessage()]));
        }

        return new Response($content);
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
