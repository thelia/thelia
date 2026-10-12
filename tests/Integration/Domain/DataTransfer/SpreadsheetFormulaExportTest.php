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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Export\Type\CustomerExport;
use Thelia\Domain\DataTransfer\Export\Type\MailingExport;
use Thelia\Domain\DataTransfer\Export\Type\OrderExport;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Model\Export;
use Thelia\Model\Lang;
use Thelia\Model\Newsletter;
use Thelia\Model\OrderAddressQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Shoppers and newsletter subscribers type their names, addresses and phones themselves;
 * an administrator exports them and opens the file in a spreadsheet. Whatever they typed
 * reaches a CSV file as text, never as a formula the spreadsheet would run.
 */
final class SpreadsheetFormulaExportTest extends IntegrationTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testTheCustomerExportWritesWhatTheCustomerTypedAsText(): void
    {
        $factory = $this->createFixtureFactory();
        $email = 'formula-'.uniqid().'@example.com';
        $customer = $factory->customer($factory->customerTitle(), [
            'firstname' => '=1+2',
            'lastname' => '@SUM(1+1)',
            'email' => $email,
        ]);
        $factory->address($customer, overrides: ['city' => '-1+cmd'])
            ->setCompany('+1+cmd')
            ->setPhone('-2+3')
            ->setCellphone('+33612345678')
            ->setIsDefault(1)
            ->save($this->getPropelConnection());

        $row = $this->csvRowHolding($email, $this->exportedFile(CustomerExport::class, 'thelia.csv'));

        foreach (["'=1+2", "'@SUM(1+1)", "'-1+cmd", "'+1+cmd", "'-2+3"] as $cell) {
            self::assertContains($cell, $row);
        }

        self::assertContains('+33612345678', $row, 'A phone number made of digits only cannot run anything.');
    }

    public function testTheOrderExportWritesTheAddressesAsText(): void
    {
        $order = $this->createFixtureFactory()->order();
        $order->setRef('FORMULA-'.uniqid())->save($this->getPropelConnection());

        $delivery = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        $delivery->setFirstname('=1+2')->setCompany('@SUM(1+1)')->setPhone('+1+cmd')->save($this->getPropelConnection());

        $invoice = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        $invoice->setCity('-1+cmd')->save($this->getPropelConnection());

        $row = $this->csvRowHolding($order->getRef(), $this->exportedFile(OrderExport::class, 'thelia.csv'));

        foreach (["'=1+2", "'@SUM(1+1)", "'+1+cmd", "'-1+cmd"] as $cell) {
            self::assertContains($cell, $row);
        }
    }

    public function testTheMailingExportWritesTheSubscriberNamesAsText(): void
    {
        $email = $this->subscriber('=1+2', '-1+cmd');

        $row = $this->csvRowHolding($email, $this->exportedFile(MailingExport::class, 'thelia.csv'));

        self::assertContains("'=1+2", $row);
        self::assertContains("'-1+cmd", $row);
    }

    public function testAJsonExportKeepsTheValuesAsTyped(): void
    {
        $email = $this->subscriber('=1+2', '-1+cmd');

        $rows = json_decode(
            (string) file_get_contents($this->exportedFile(MailingExport::class, 'thelia.json')),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $row = array_values(array_filter($rows, static fn (array $row): bool => \in_array($email, $row, true)))[0] ?? [];

        self::assertContains('=1+2', $row);
        self::assertContains('-1+cmd', $row);
    }

    private function subscriber(string $firstname, string $lastname): string
    {
        $email = 'formula-'.uniqid().'@example.com';

        (new Newsletter())
            ->setEmail($email)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setLocale('en_US')
            ->setUnsubscribed(0)
            ->save($this->getPropelConnection());

        return $email;
    }

    /**
     * @param class-string $handleClass
     */
    private function exportedFile(string $handleClass, string $serializerId): string
    {
        $export = (new Export())->setRef('formula-test')->setHandleClass($handleClass);

        $event = $this->getService(ExportHandler::class)->export(
            $export,
            $this->getService(SerializerManager::class)->get($serializerId),
            null,
            Lang::getDefaultLanguage(),
        );

        $this->files[] = $event->getFilePath();

        return $event->getFilePath();
    }

    /**
     * @return list<string|null>
     */
    private function csvRowHolding(string $value, string $file): array
    {
        $handle = fopen($file, 'r');

        try {
            while (false !== $row = fgetcsv($handle, null, ',', '"', '')) {
                if (\in_array($value, $row, true)) {
                    return $row;
                }
            }
        } finally {
            fclose($handle);
        }

        self::fail("No row of $file holds $value.");
    }
}
