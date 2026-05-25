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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\Newsletter;

final class NewsletterImporter extends AbstractDemoImporter
{
    public function priority(): int
    {
        return 105;
    }

    public function description(): string
    {
        return 'Newsletter subscribers';
    }

    public function import(DemoImportContext $context): void
    {
        foreach ($this->readCsv($context->dataDir.'newsletter.csv') as $data) {
            (new Newsletter())
                ->setEmail($data[0])
                ->setFirstname($data[1])
                ->setLastname($data[2])
                ->setLocale($data[3])
                ->setUnsubscribed(0)
                ->save($context->connection);
        }
    }
}
