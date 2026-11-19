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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Thelia\Domain\Search\Dictionary\SearchDictionary;
use Thelia\Model\Event\LangEvent;
use Thelia\Model\Event\SearchSynonymEvent;
use Thelia\Model\Event\SearchTermRedirectEvent;

final readonly class SearchDictionaryCacheListener
{
    public function __construct(private SearchDictionary $dictionary)
    {
    }

    #[AsEventListener(event: SearchSynonymEvent::POST_SAVE)]
    #[AsEventListener(event: SearchSynonymEvent::POST_DELETE)]
    #[AsEventListener(event: SearchTermRedirectEvent::POST_SAVE)]
    #[AsEventListener(event: SearchTermRedirectEvent::POST_DELETE)]
    #[AsEventListener(event: LangEvent::POST_SAVE)]
    #[AsEventListener(event: LangEvent::POST_DELETE)]
    public function forget(): void
    {
        $this->dictionary->forget();
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    #[AsEventListener(event: WorkerMessageFailedEvent::class)]
    public function forgetAfterTheRequest(): void
    {
        $this->dictionary->forgetAfterTheRequest();
    }
}
