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

namespace BackOfficeDefaultTwigBundle\Controller;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;
use Twig\Environment;

#[Route('/admin/newsletter', name: 'admin.newsletter.')]
final class NewsletterController
{
    private const RESOURCE = 'admin.newsletter';
    private const LIST_ROUTE = 'admin.newsletter.default';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/newsletter/list.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
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

        $subscribers = NewsletterQuery::create()->orderByCreatedAt(\Propel\Runtime\ActiveQuery\Criteria::DESC)->find();
        $rows = [];
        foreach ($subscribers as $subscriber) {
            \assert($subscriber instanceof Newsletter);
            $rows[] = $this->subscriberToRow($subscriber);
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
            'export_url' => $this->urls->generate('admin.newsletter.export'),
        ]));
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $subscriber = NewsletterQuery::create()->findPk((int) $request->get('newsletter_id', 0));
        if ($subscriber === null) {
            return new \Symfony\Component\HttpFoundation\RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $event = new NewsletterEvent($subscriber->getEmail(), (string) $subscriber->getLocale());
        $event->setNewsletter($subscriber);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::NEWSLETTER_UNSUBSCRIBE,
            actionLabel: 'Newsletter subscriber removal',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $response = new StreamedResponse(function (): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['email', 'firstname', 'lastname', 'locale', 'created_at']);

            foreach (NewsletterQuery::create()->orderByCreatedAt()->find() as $subscriber) {
                \assert($subscriber instanceof Newsletter);
                fputcsv($handle, [
                    (string) $subscriber->getEmail(),
                    (string) $subscriber->getFirstname(),
                    (string) $subscriber->getLastname(),
                    (string) $subscriber->getLocale(),
                    $subscriber->getCreatedAt() instanceof \DateTimeInterface ? $subscriber->getCreatedAt()->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="newsletter-subscribers-'.date('Y-m-d').'.csv"');

        return $response;
    }

    /** @return array<string, mixed> */
    private function subscriberToRow(Newsletter $subscriber): array
    {
        $id = (int) $subscriber->getId();
        $actions = [
            new RowAction(kind: 'delete', label: $this->translator->trans('Unsubscribe'), href: $this->urls->generate('admin.newsletter.delete', ['newsletter_id' => $id]), grantedAttribute: AccessManager::DELETE, grantedSubject: self::RESOURCE),
        ];

        return [
            'id' => $id,
            'email' => (string) $subscriber->getEmail(),
            'firstname' => (string) $subscriber->getFirstname(),
            'lastname' => (string) $subscriber->getLastname(),
            'locale' => (string) $subscriber->getLocale(),
            'created_at' => $subscriber->getCreatedAt() instanceof \DateTimeInterface ? $subscriber->getCreatedAt()->format('Y-m-d H:i') : '',
            '_actions' => $actions,
        ];
    }
}
