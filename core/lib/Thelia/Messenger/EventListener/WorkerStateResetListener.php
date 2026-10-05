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

namespace Thelia\Messenger\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Core\Cache\ConfigCacheService;
use Thelia\Core\Routing\Rewriting\RewritingUrlMemoizer;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Lang;
use Thelia\Model\ModuleConfigQuery;

/**
 * Starts every job a worker takes on from the state a fresh command starts from.
 *
 * A worker is one process handling thousands of jobs, and the core keeps a few
 * things in memory for as long as a request or a command lasts: the settings, the
 * active languages, the default country, the module settings, the rewritten URLs,
 * the currency of the prices. Each of them is forgotten when a command starts
 * (ConsoleEvents::COMMAND) or a request comes in; without this, a worker started on
 * Monday still applies on Friday the settings it read on Monday, and a job that set
 * a language hands it to the next one.
 *
 * Every Thelia listener of ConsoleEvents::COMMAND has its counterpart here; the
 * integration test of this class fails when one is added without it.
 */
final readonly class WorkerStateResetListener
{
    public function __construct(
        private ConfigCacheService $configCacheService,
        private RewritingUrlMemoizer $rewritingUrlMemoizer,
        private ProductPriceCurrencyListener $productPriceCurrencyListener,
        private Translator $translator,
    ) {
    }

    #[AsEventListener(priority: 4096)]
    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        // The settings are read again from the shared entry, which a write in the
        // back office empties: a change made since the previous job is seen.
        ConfigQuery::resetCache();
        $this->configCacheService->initCacheConfigs();

        Lang::resetActiveLangsCache();
        Country::resetDefaultCountryCache();
        ModuleConfigQuery::resetConfigCache();
        $this->rewritingUrlMemoizer->clear();
        $this->productPriceCurrencyListener->forgetCurrentCurrency();

        // There is no request in a worker, so the translator answers with the
        // locale it was last given: the one of the previous job, if it set one.
        $this->translator->setLocale(Lang::getDefaultLanguage()->getLocale());
    }
}
