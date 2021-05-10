<?php

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
use Thelia\Core\Service\ConfigCacheService;
use Thelia\Model\Event\ConfigEvent;

class ModelConfigListener implements EventSubscriberInterface
{
    /**
     * @var ConfigCacheService
     */
    private $configCacheService;

    public function __construct(
        ConfigCacheService $configCacheService
    ) {
        $this->configCacheService = $configCacheService;
    }

    public function resetCache(): void
    {
        $this->configCacheService->initCacheConfigs(true);
    }

    /**
     * {@inheritdoc}
     * api.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvent::POST_SAVE => ['resetCache', 128],
            ConfigEvent::POST_DELETE => ['resetCache', 128],
        ];
    }
}
