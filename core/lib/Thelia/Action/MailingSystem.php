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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\MailingSystem\MailingSystemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

class MailingSystem extends BaseAction implements EventSubscriberInterface
{
    public function __construct(private readonly ConfigCacheService $configCache, private readonly EventDispatcherInterface $dispatcher, private $kernelCacheDir)
    {
    }

    public function update(MailingSystemEvent $event): void
    {
        if ($event->getEnabled()) {
            ConfigQuery::enableSmtp();
        } else {
            ConfigQuery::disableSmtp();
        }

        ConfigQuery::setSmtpHost($event->getHost());
        ConfigQuery::setSmtpPort($event->getPort());
        ConfigQuery::setSmtpEncryption($event->getEncryption());
        ConfigQuery::setSmtpUsername($event->getUsername());
        ConfigQuery::setSmtpPassword($event->getPassword());
        ConfigQuery::setSmtpAuthMode($event->getAuthMode());
        ConfigQuery::setSmtpTimeout($event->getTimeout());
        ConfigQuery::setSmtpSourceIp($event->getSourceIp());

        $cacheEvent = new CacheEvent($this->kernelCacheDir);
        $this->dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
        $this->configCache->initCacheConfigs(true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::MAILING_SYSTEM_UPDATE => ['update', 128],
        ];
    }
}
