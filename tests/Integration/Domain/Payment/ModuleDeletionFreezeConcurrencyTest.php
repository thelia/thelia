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

namespace Thelia\Tests\Integration\Domain\Payment;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;

/**
 * Deleting a module while a settlement holds the order of one of its parts: the two take
 * the order, then its parts, and neither is chosen as the victim of a deadlock.
 *
 * The settlement is a process of its own that locks the order, waits, then locks the parts
 * (settle-order-in-another-process.php), the way the core does; the fixtures are committed
 * and removed by hand.
 */
#[Group('concurrency')]
final class ModuleDeletionFreezeConcurrencyTest extends IntegrationTestCase
{
    use ForgetsPooledModels;

    protected bool $useTransaction = false;

    private const DEADLOCK = 1213;

    private ?ConnectionInterface $otherSession = null;

    /** @var list<int> */
    private array $moduleIds = [];

    private ?int $orderId = null;

    /** @var list<int> */
    private array $orderAddressIds = [];

    private ?int $cartId = null;

    private ?int $customerId = null;

    private string $marker = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->otherSession = $this->openOtherSession();
        $this->marker = sys_get_temp_dir().'/freeze-concurrency-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        try {
            $connection = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);

            while ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->deleteFixtures();
        } finally {
            self::forgetPooledModels();
            Propel::disableInstancePooling();

            parent::tearDown();
        }
    }

    public function testDeletingTheModuleOfAPartWhileItsOrderIsBeingSettledDoesNotDeadlock(): void
    {
        $module = $this->committedModule('FreezeConcurrencyModule');
        $order = $this->committedOrderWithPartOf($module);

        $process = $this->settleInAnotherProcess($order);
        $this->waitForTheMarker($process);

        $failure = null;

        try {
            $this->getService(EventDispatcherInterface::class)->dispatch(new ModuleDeleteEvent($module->getId()), TheliaEvents::MODULE_DELETE);
        } catch (\Throwable $throwable) {
            $failure = $throwable;
        }

        $settlement = $this->waitFor($process);

        self::assertNotSame(self::DEADLOCK, $settlement['errorCode'], 'The settlement was chosen as the victim of a deadlock.');
        self::assertNull($failure, 'The deletion failed: '.$failure?->getMessage());
        self::assertNull($settlement['errorCode']);

        $statement = $this->otherSession?->prepare('SELECT `payment_module_id`, `payment_module_title` FROM `order_payment_part` WHERE `order_id` = ?');
        self::assertNotNull($statement);
        $statement->execute([$order->getId()]);
        self::assertSame([['payment_module_id' => null, 'payment_module_title' => 'FreezeConcurrencyModule']], $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return resource
     */
    private function settleInAnotherProcess(Order $order)
    {
        $configuration = $this->connectionConfiguration();
        $environment = array_merge(getenv(), [
            'FREEZE_TEST_DSN' => (string) $configuration['dsn'],
            'FREEZE_TEST_USER' => (string) ($configuration['user'] ?? ''),
            'FREEZE_TEST_PASSWORD' => (string) ($configuration['password'] ?? ''),
        ]);

        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 3).'/Support/Payment/settle-order-in-another-process.php', (string) $order->getId(), $this->marker, '2000'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    /**
     * @param resource $process
     */
    private function waitForTheMarker($process): void
    {
        $deadline = microtime(true) + 30;

        while (!is_file($this->marker)) {
            if (!proc_get_status($process)['running']) {
                self::fail('The settlement process ended early: '.stream_get_contents($this->pipes[(int) $process][2]));
            }

            if (microtime(true) > $deadline) {
                self::fail('The settlement process never locked the order.');
            }

            usleep(20000);
        }
    }

    /**
     * @param resource $process
     *
     * @return array{errorCode: int|null}
     */
    private function waitFor($process): array
    {
        $output = stream_get_contents($this->pipes[(int) $process][1]);
        $errors = stream_get_contents($this->pipes[(int) $process][2]);
        proc_close($process);

        $decoded = json_decode((string) $output, true);
        self::assertIsArray($decoded, 'The settlement process answered: '.$output.$errors);

        return $decoded;
    }

    private function committedModule(string $code): Module
    {
        $module = (new Module())
            ->setCode($code)
            // A root namespace of its own: deleting a module removes the exports and imports
            // of its root namespace, which for a "Thelia\\…" one is the whole core, for good.
            ->setFullNamespace('FreezeConcurrencyMissing\\'.$code)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED);
        $module->save();
        $this->moduleIds[] = (int) $module->getId();

        $lang = LangQuery::create()->findOne() ?? throw new \RuntimeException('No lang.');
        $module->setLocale($lang->getLocale())->setTitle($code)->save();

        return $module;
    }

    private function committedOrderWithPartOf(Module $module): Order
    {
        $title = CustomerTitleQuery::create()->findOne() ?? throw new \RuntimeException('No customer title.');
        $country = CountryQuery::create()->findOne() ?? throw new \RuntimeException('No country.');
        $currency = CurrencyQuery::create()->findOne() ?? throw new \RuntimeException('No currency.');
        $lang = LangQuery::create()->findOne() ?? throw new \RuntimeException('No lang.');
        $status = OrderStatusQuery::create()->findOneByCode(\Thelia\Model\OrderStatus::CODE_NOT_PAID) ?? throw new \RuntimeException('No status.');
        $delivery = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? throw new \RuntimeException('No delivery module.');

        $customer = (new Customer())
            ->setTitleId($title->getId())
            ->setFirstname('Jane')
            ->setLastname('Doe')
            ->setEmail(\sprintf('freeze-concurrency-%s@test.com', bin2hex(random_bytes(6))))
            ->setPassword('password');
        $customer->save();
        $this->customerId = (int) $customer->getId();

        $cart = (new Cart())
            ->setCustomerId($customer->getId())
            ->setCurrencyId($currency->getId())
            ->setToken('freeze-concurrency-'.bin2hex(random_bytes(6)));
        $cart->save();
        $this->cartId = (int) $cart->getId();

        $addressIds = [];

        foreach ([1, 2] as $ignored) {
            $address = (new OrderAddress())
                ->setCustomerTitleId($title->getId())
                ->setFirstname('Jane')
                ->setLastname('Doe')
                ->setAddress1('1 Main Street')
                ->setZipcode('75001')
                ->setCity('Paris')
                ->setCountryId($country->getId());
            $address->save();
            $addressIds[] = $this->orderAddressIds[] = (int) $address->getId();
        }

        $order = (new Order())
            ->setCustomerId($customer->getId())
            ->setInvoiceOrderAddressId($addressIds[0])
            ->setDeliveryOrderAddressId($addressIds[1])
            ->setCurrencyId($currency->getId())
            ->setCurrencyRate(1.0)
            ->setDeliveryModuleId($delivery->getId())
            ->setStatusId($status->getId())
            ->setLangId($lang->getId())
            ->setCartId($cart->getId());
        $order->save();
        $this->orderId = (int) $order->getId();

        (new OrderPaymentPart())
            ->setOrderId($order->getId())
            ->setPaymentModuleId($module->getId())
            ->setAmount('10')
            ->setIsPrimary(true)
            ->setPosition(0)
            ->save();

        return $order;
    }

    private function deleteFixtures(): void
    {
        $session = $this->otherSession;

        if (null === $session) {
            return;
        }

        if (is_file($this->marker)) {
            unlink($this->marker);
        }

        foreach ($this->pipes as $pipes) {
            foreach ($pipes as $pipe) {
                if (\is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }
        $this->pipes = [];

        if (null !== $this->orderId) {
            $session->exec('DELETE FROM `order_payment_part` WHERE `order_id` = '.$this->orderId);
            $session->exec('DELETE FROM `order_version` WHERE `id` = '.$this->orderId);
            $session->exec('DELETE FROM `order` WHERE `id` = '.$this->orderId);
        }

        foreach ($this->orderAddressIds as $addressId) {
            $session->exec('DELETE FROM `order_address` WHERE `id` = '.$addressId);
        }

        if (null !== $this->cartId) {
            $session->exec('DELETE FROM `cart` WHERE `id` = '.$this->cartId);
        }

        if (null !== $this->customerId) {
            $session->exec('DELETE FROM `customer_version` WHERE `id` = '.$this->customerId);
            $session->exec('DELETE FROM `customer` WHERE `id` = '.$this->customerId);
        }

        foreach ($this->moduleIds as $moduleId) {
            $session->exec('DELETE FROM `module_i18n` WHERE `id` = '.$moduleId);
            $session->exec('DELETE FROM `module` WHERE `id` = '.$moduleId);
        }

        $this->orderId = $this->cartId = $this->customerId = null;
        $this->orderAddressIds = $this->moduleIds = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfiguration(): array
    {
        $manager = Propel::getServiceContainer()->getConnectionManager(OrderPaymentPartTableMap::DATABASE_NAME);

        if (!$manager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $manager->getConfiguration();
    }

    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $this->connectionConfiguration();
        $manager = $serviceContainer->getConnectionManager(OrderPaymentPartTableMap::DATABASE_NAME);

        return ConnectionFactory::create($manager->getConfiguration(), $serviceContainer->getAdapter(OrderPaymentPartTableMap::DATABASE_NAME));
    }
}
