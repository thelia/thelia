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

namespace Thelia\Domain\Search\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargets;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargetType;
use Thelia\Model\Event\BrandEvent;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Event\ContentEvent;
use Thelia\Model\Event\FolderEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\SearchTermRedirectQuery;

final class SearchTermRedirectTargetListener implements ResetInterface
{
    /** @var array<string, array{string, int}> */
    private array $deletedTargets = [];

    public function __construct(
        private readonly SearchRedirectTargets $targets,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: ProductEvent::POST_DELETE)]
    public function onProductDelete(ProductEvent $event): void
    {
        $this->remember(SearchRedirectTargetType::PRODUCT, $event->getModel()->getId());
    }

    #[AsEventListener(event: CategoryEvent::POST_DELETE)]
    public function onCategoryDelete(CategoryEvent $event): void
    {
        $this->remember(SearchRedirectTargetType::CATEGORY, $event->getModel()->getId());
    }

    #[AsEventListener(event: ContentEvent::POST_DELETE)]
    public function onContentDelete(ContentEvent $event): void
    {
        $this->remember(SearchRedirectTargetType::CONTENT, $event->getModel()->getId());
    }

    #[AsEventListener(event: FolderEvent::POST_DELETE)]
    public function onFolderDelete(FolderEvent $event): void
    {
        $this->remember(SearchRedirectTargetType::FOLDER, $event->getModel()->getId());
    }

    #[AsEventListener(event: BrandEvent::POST_DELETE)]
    public function onBrandDelete(BrandEvent $event): void
    {
        $this->remember(SearchRedirectTargetType::BRAND, $event->getModel()->getId());
    }

    #[AsEventListener(event: KernelEvents::TERMINATE, priority: 10)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE, priority: 10)]
    #[AsEventListener(event: WorkerMessageHandledEvent::class, priority: 10)]
    #[AsEventListener(event: WorkerMessageFailedEvent::class, priority: 10)]
    public function deactivateTheRedirectionsOfTheDeletedTargets(): void
    {
        $deletedTargets = $this->deletedTargets;
        $this->deletedTargets = [];

        foreach ($deletedTargets as [$type, $id]) {
            $this->deactivate($type, $id);
        }
    }

    public function reset(): void
    {
        $this->deletedTargets = [];
    }

    private function remember(string $type, ?int $id): void
    {
        if (null !== $id) {
            $this->deletedTargets[$type.'#'.$id] = [$type, $id];
        }
    }

    private function deactivate(string $type, int $id): void
    {
        try {
            if (null !== $this->targets->find($type, $id)) {
                return;
            }

            $redirects = SearchTermRedirectQuery::create()
                ->filterByTargetType($type)
                ->filterByTargetId($id)
                ->filterByActive(1)
                ->find();

            foreach ($redirects as $redirect) {
                $redirect->setActive(0)->save();
            }
        } catch (\Throwable $exception) {
            $this->logger->error('The search redirections to the deleted {type} {id} could not be deactivated ({exception}).', [
                'type' => $type,
                'id' => $id,
                'exception' => $exception::class,
            ]);
        }
    }
}
