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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Model\Event\ConfigEvent;
use Thelia\Service\ConfigCacheService;

readonly class ModelConfigListener implements EventSubscriberInterface
{
    public function __construct(
        private ConfigCacheService $configCacheService,
    ) {
    }

    public function resetCache(): void
    {
        $this->configCacheService->initCacheConfigs(true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvent::POST_SAVE => ['resetCache', 128],
            ConfigEvent::POST_DELETE => ['resetCache', 128],
        ];
    }
}
