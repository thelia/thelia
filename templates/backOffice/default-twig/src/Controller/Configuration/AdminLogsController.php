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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\AdminQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ResourceQuery;
use Twig\Environment;

#[Route('/admin/configuration/adminLogs', name: 'admin.configuration.admin-logs.')]
final class AdminLogsController
{
    private const RESOURCE = AdminResources::ADMIN_LOG;
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/admin-logs/list.html.twig';

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
    ) {
    }

    #[Route('', name: 'view', methods: ['GET'])]
    public function view(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildContext([], [])));
    }

    #[Route('/logger', name: 'logger', methods: ['POST'])]
    public function logger(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $admins = (array) $request->request->all('admins');
        $resources = (array) $request->request->all('resources');
        $modules = (array) $request->request->all('modules');
        $fromDate = $request->request->get('fromDate') ?: null;
        $toDate = $request->request->get('toDate') ?: null;

        $entries = AdminLogQuery::getEntries(
            $admins !== [] ? $admins : null,
            $fromDate,
            $toDate,
            array_merge($resources, $modules) ?: null,
        );

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildContext(
            entries: $this->formatEntries($entries),
            selected: [
                'admins' => $admins,
                'resources' => $resources,
                'modules' => $modules,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
            ],
        )));
    }

    /**
     * @param list<array{date: string, admin: string, resource: string, action: string, resource_id: ?int, message: string}> $entries
     * @param array<string, mixed> $selected
     *
     * @return array<string, mixed>
     */
    private function buildContext(array $entries, array $selected): array
    {
        return [
            'entries' => $entries,
            'admins' => AdminQuery::create()->orderByLogin()->find(),
            'resources' => ResourceQuery::create()->orderByCode()->find(),
            'modules' => ModuleQuery::create()->orderByCode()->find(),
            'selected_admins' => $selected['admins'] ?? [],
            'selected_resources' => $selected['resources'] ?? [],
            'selected_modules' => $selected['modules'] ?? [],
            'from_date' => $selected['fromDate'] ?? $this->defaultFromDate(),
            'to_date' => $selected['toDate'] ?? $this->defaultToDate(),
            'submitted' => $selected !== [],
        ];
    }

    /**
     * @param iterable<AdminLog> $entries
     *
     * @return list<array{date: string, admin: string, resource: string, action: string, resource_id: ?int, message: string}>
     */
    private function formatEntries(iterable $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'date' => $entry->getCreatedAt()?->format('Y-m-d H:i:s') ?? '',
                'admin' => (string) $entry->getAdminLogin(),
                'resource' => (string) $entry->getResource(),
                'action' => (string) $entry->getAction(),
                'resource_id' => $entry->getResourceId(),
                'message' => (string) $entry->getMessage(),
            ];
        }

        return $rows;
    }

    private function defaultFromDate(): string
    {
        return (new \DateTimeImmutable('-7 days'))->format('Y-m-d');
    }

    private function defaultToDate(): string
    {
        return (new \DateTimeImmutable('today'))->format('Y-m-d');
    }
}
