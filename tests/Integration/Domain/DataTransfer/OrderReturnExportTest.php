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

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\Type\OrderReturnExport;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The returns export writes one row per returned line, with what the merchant reads in the
 * queue of the back office: the reason or the customer's words, the expected resolution,
 * the wished exchange and whether the return is settled.
 */
final class OrderReturnExportTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    public function testAReturnedLineIsOneRow(): void
    {
        $order = $this->orderWithALine(['Helmet size' => 'L', 'Colour' => 'Black']);
        $this->returnOf($order, OrderReturnStatus::CODE_SETTLED, OrderReturn::RESOLUTION_EXCHANGE, 'Too small', null, 'The same one in XL');

        self::assertSame([[
            'Date' => '2024-03-10 09:30:00',
            'Order' => $order->getRef(),
            'Product' => 'Returned helmet',
            'Combination values' => 'L / Black',
            'Ref' => 'HELMET-L',
            'Reason' => 'Too small',
            'Type' => OrderReturn::RESOLUTION_EXCHANGE,
            'Expected exchange' => 'The same one in XL',
            'Settled' => 1,
            'Comment' => null,
        ]], $this->rowsOf($order, $this->locale('en_US')));
    }

    public function testTheCustomersWordsAreKeptWhenAReasonWasPicked(): void
    {
        $order = $this->orderWithALine(['Helmet size' => 'L']);
        $this->returnOf($order, OrderReturnStatus::CODE_REQUESTED, OrderReturn::RESOLUTION_REFUND, 'Damaged on arrival', 'The visor is scratched.', null);

        $row = $this->rowsOf($order, $this->locale('en_US'))[0];

        self::assertSame('Damaged on arrival', $row['Reason']);
        self::assertSame('The visor is scratched.', $row['Comment']);
    }

    public function testAStatusOfTheShopThatStandsForSettledIsSettled(): void
    {
        $status = (new OrderReturnStatus())->setCode('refunded_'.uniqid())->setEquivalentCode(OrderReturnStatus::CODE_SETTLED);
        $status->save($this->getPropelConnection());
        $order = $this->orderWithALine([]);
        $this->returnOf($order, $status->getCode(), OrderReturn::RESOLUTION_REFUND, 'Too big', null, null);

        self::assertSame(1, $this->rowsOf($order, $this->locale('en_US'))[0]['Settled']);
        self::assertNull($this->rowsOf($order, $this->locale('en_US'))[0]['Combination values'], 'A line sold without any attribute.');
    }

    /**
     * The headers are translated in the language picked on the export screen: in French, the
     * ones of the returns file shops already read.
     */
    public function testTheHeadersAreTranslated(): void
    {
        $headers = array_values((new \ReflectionProperty(OrderReturnExport::class, 'orderAndAliases'))->getDefaultValue());
        $translations = [];

        foreach (['fr_FR', 'de_DE', 'en_US'] as $locale) {
            $catalogue = require THELIA_LIB.'Config/I18n/'.$locale.'.php';
            $translations[$locale] = array_map(static fn (string $header): ?string => $catalogue[$header] ?? null, $headers);
        }

        self::assertSame(['Date', 'Commande', 'Produit', 'Déclinaison', 'Ref', 'Motif', 'Type', 'Échange souhaité', 'Validation', 'Commentaire'], $translations['fr_FR']);
        self::assertNotContains(null, $translations['de_DE']);
        self::assertSame($headers, $translations['en_US']);
    }

    public function testWithoutAReasonTheRowGivesTheCustomersOwnWords(): void
    {
        $order = $this->orderWithALine(['Helmet size' => 'M']);
        $this->returnOf($order, OrderReturnStatus::CODE_REQUESTED, OrderReturn::RESOLUTION_REFUND, null, 'The visor is scratched.', null);

        $row = $this->rowsOf($order, $this->locale('en_US'))[0];

        self::assertSame('The visor is scratched.', $row['Reason']);
        self::assertSame(0, $row['Settled'], 'A return still requested is not settled.');
        self::assertNull($row['Expected exchange']);
    }

    public function testOnlyTheReturnsOpenedInThePeriodAreExported(): void
    {
        $order = $this->orderWithALine(['Helmet size' => 'S']);
        $this->returnOf($order, OrderReturnStatus::CODE_REQUESTED, OrderReturn::RESOLUTION_REFUND, 'Too big', null, null);

        self::assertCount(1, $this->rowsOf($order, $this->locale('en_US'), ['start' => new \DateTime('2024-03-01 00:00:00'), 'end' => new \DateTime('2024-03-31 23:59:59')]));
        self::assertCount(0, $this->rowsOf($order, $this->locale('en_US'), ['start' => new \DateTime('2024-04-01 00:00:00'), 'end' => new \DateTime('2024-04-30 23:59:59')]));
        self::assertCount(1, $this->rowsOf($order, $this->locale('en_US'), ['start' => new \DateTime('2024-03-01 00:00:00'), 'end' => null]), 'A start alone runs up to today.');
        self::assertCount(0, $this->rowsOf($order, $this->locale('en_US'), ['start' => null, 'end' => new \DateTime('2024-02-29 23:59:59')]), 'An end alone runs from the first return.');
    }

    public function testAWishASpreadsheetWouldRunIsExportedAsText(): void
    {
        $order = $this->orderWithALine(['Helmet size' => 'L']);
        $this->returnOf($order, OrderReturnStatus::CODE_REQUESTED, OrderReturn::RESOLUTION_EXCHANGE, null, '=HYPERLINK("http://example.test")', '=1+1');

        $row = $this->rowsOf($order, $this->locale('en_US'))[0];

        self::assertSame('\'=HYPERLINK("http://example.test")', $row['Reason']);
        self::assertSame("'=1+1", $row['Expected exchange']);
    }

    public function testAProductOrACombinationThatStartsLikeAFormulaIsExportedAsText(): void
    {
        $order = $this->orderWithALine(['Helmet size' => '=2+2']);
        $order->getOrderProducts()->getFirst()->setTitle('@SUM(A1)')->save($this->getPropelConnection());
        $this->returnOf($order, OrderReturnStatus::CODE_REQUESTED, OrderReturn::RESOLUTION_REFUND, 'Too big', null, null);

        $row = $this->rowsOf($order, $this->locale('en_US'))[0];

        self::assertSame("'@SUM(A1)", $row['Product']);
        self::assertSame("'=2+2", $row['Combination values']);
    }

    public function testTheExportIsRegisteredInTheCatalogue(): void
    {
        $export = ExportQuery::create()->findOneByRef('thelia.export.order_returns');

        self::assertNotNull($export);
        self::assertSame(OrderReturnExport::class, $export->getHandleClass());
        self::assertSame('thelia.export.orders', $export->getExportCategory()?->getRef());
    }

    /**
     * @param array<string, string> $attributes attribute title => value title, in the order of the line
     */
    private function orderWithALine(array $attributes): Order
    {
        $order = $this->factory->order($this->factory->customer($this->factory->customerTitle()));
        $order->setRef('RETURN-'.uniqid())->save($this->getPropelConnection());

        $line = (new OrderProduct())
            ->setOrderId((int) $order->getId())
            ->setProductRef('HELMET')
            ->setProductSaleElementsRef('HELMET-L')
            ->setProductSaleElementsId(0)
            ->setTitle('Returned helmet')
            ->setQuantity(2.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $line->save($this->getPropelConnection());

        foreach ($attributes as $attribute => $value) {
            (new OrderProductAttributeCombination())
                ->setOrderProductId((int) $line->getId())
                ->setAttributeTitle($attribute)
                ->setAttributeAvTitle($value)
                ->save($this->getPropelConnection());
        }

        return $order;
    }

    private function returnOf(Order $order, string $statusCode, string $resolution, ?string $reasonTitle, ?string $comment, ?string $wish): void
    {
        $return = (new OrderReturn())
            ->setOrderId((int) $order->getId())
            ->setCustomerId((int) $order->getCustomerId())
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode($statusCode))
            ->setReasonTitle($reasonTitle)
            ->setCustomerComment($comment)
            ->setExpectedResolution($resolution)
            ->setExpectedExchange($wish)
            ->setCreatedAt(new \DateTime('2024-03-10 09:30:00'));
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturnId((int) $return->getId())
            ->setOrderProductId((int) $order->getOrderProducts()->getFirst()->getId())
            ->setQuantity(1.0)
            ->save($this->getPropelConnection());
    }

    private function locale(string $locale): Lang
    {
        $lang = LangQuery::create()->findOneByLocale($locale);
        self::assertNotNull($lang);

        return $lang;
    }

    /**
     * Rows of one order as written to the file: past beforeSerialize() and the aliases.
     *
     * @param array<string, \DateTime>|null $rangeDate
     *
     * @return list<array<string, mixed>>
     */
    private function rowsOf(Order $order, Lang $lang, ?array $rangeDate = null): array
    {
        $export = new OrderReturnExport();
        $export->setLang($lang);
        $export->setRangeDate($rangeDate);

        $rows = [];

        try {
            foreach ($export as $row) {
                if (($row['order_ref'] ?? null) === $order->getRef()) {
                    $rows[] = $export->applyOrderAndAliases($export->beforeSerialize($row));
                }
            }
        } catch (DataTransferNoDataFoundException) {
            // A period without any return at all.
        }

        return $rows;
    }
}
