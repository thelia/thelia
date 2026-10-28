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

namespace Thelia\Domain\Ai\Tool\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Domain\Ai\Tool\Service\AiToolApprovalRepository;
use Thelia\Model\Event\AdminEvent;

/**
 * Rejects the proposals that wait for an administrator who is about to be deleted (note `admin_deleted`).
 *
 * It runs in the transaction of the deletion, before the foreign keys of the approvals go to NULL: the rows
 * keep the login of the administrator, and nobody is left to answer for a proposal he made.
 */
final readonly class AiToolApprovalAdminDeletedListener
{
    public function __construct(private AiToolApprovalRepository $approvals)
    {
    }

    #[AsEventListener(event: AdminEvent::PRE_DELETE)]
    public function onAdminPreDelete(AdminEvent $event): void
    {
        $adminId = $event->getModel()->getId();

        $this->approvals->rejectPendingProposedBy($adminId, new \DateTimeImmutable());
    }
}
