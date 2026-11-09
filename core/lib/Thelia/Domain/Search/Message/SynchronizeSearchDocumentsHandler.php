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

namespace Thelia\Domain\Search\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Thelia\Domain\Search\SearchIndexer;

#[AsMessageHandler]
final readonly class SynchronizeSearchDocumentsHandler
{
    public function __construct(private SearchIndexer $indexer)
    {
    }

    public function __invoke(SynchronizeSearchDocuments $message): void
    {
        $this->indexer->synchronize($message->family, $message->ids, $message->locales);
    }
}
