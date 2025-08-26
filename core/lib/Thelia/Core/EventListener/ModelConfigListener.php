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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Model\Event\ConfigEvent;

readonly class ModelConfigListener
{
    public function __construct(
        private ConfigCacheService $configCacheService,
    ) {
    }

    #[AsEventListener(event: ConfigEvent::POST_SAVE, priority: 128)]
    #[AsEventListener(event: ConfigEvent::POST_DELETE, priority: 128)]
    public function resetCache(): void
    {
        $this->configCacheService->initCacheConfigs(true);
    }
}
