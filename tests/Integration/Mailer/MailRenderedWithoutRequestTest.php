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

namespace Thelia\Tests\Integration\Mailer;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Thelia\Action\Order as OrderAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Domain\Order\Service\OrderStatusTransitionGuard;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Tools\URL;

/**
 * A mail rendered by a worker, a console command or a script has no HTTP request: the request stack is
 * empty, and the loops of its template must still answer (the cart of nobody is empty, the language is
 * the default one) instead of failing the whole template.
 */
final class MailRenderedWithoutRequestTest extends IntegrationTestCase
{
    /** @var list<Request> */
    private array $poppedRequests = [];

    protected function tearDown(): void
    {
        $stack = $this->getService(RequestStack::class);

        foreach (array_reverse($this->poppedRequests) as $request) {
            $stack->push($request);
        }

        $this->poppedRequests = [];

        parent::tearDown();
    }

    public function testTheConfirmationMailIsRenderedWithoutRequest(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->customer($factory->customerTitle()));
        $factory->orderProduct($order);
        $parameters = $this->parametersTheCoreSendsFor($order);

        $this->dropTheRequests();

        $mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        );
        $email = $mailerFactory->createEmailMessage(
            'order_confirmation',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            $parameters,
            'en_US',
        );

        self::assertNotSame('', (string) $email->getHtmlBody());
        self::assertNotSame('', (string) $email->getTextBody());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('loopsWithoutLangArgument')]
    public function testALoopAnswersWithoutRequest(string $loop, array $arguments): void
    {
        $this->dropTheRequests();

        $rows = iterator_to_array($this->getService(LoopExecutor::class)->execute($loop, $arguments));

        self::assertIsArray($rows);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function loopsWithoutLangArgument(): iterable
    {
        yield 'currency' => ['currency', []];
        yield 'product' => ['product', []];
        yield 'product of the route' => ['product', ['current' => true]];
        yield 'product with its default currency' => ['product', ['with_prev_next_info' => true]];
        yield 'category' => ['category', []];
        yield 'category of the route' => ['category', ['current' => true]];
        yield 'content of the route' => ['content', ['current' => true]];
        yield 'content excluding the route' => ['content', ['exclude_current' => true]];
        yield 'folder excluding the route' => ['folder', ['exclude_current' => true]];
        yield 'brand excluding the route' => ['brand', ['exclude_current' => true]];
        yield 'sale' => ['sale', []];
        yield 'sale in amount' => ['sale', ['active' => '*']];
        yield 'product sale elements' => ['product_sale_elements', ['product' => 1]];
        yield 'cart' => ['cart', []];
        yield 'payment' => ['payment', []];
        yield 'delivery' => ['delivery', []];
        yield 'coupon in use' => ['coupon', ['in_use' => true]];
        yield 'generic' => ['generic', ['table_name' => 'config']];
    }

    public function testTheDefaultLanguageServesALoopWithoutRequest(): void
    {
        $this->dropTheRequests();

        $rows = iterator_to_array($this->getService(LoopExecutor::class)->execute('currency', []));

        self::assertNotSame([], $rows);
        self::assertInstanceOf(Lang::class, Lang::getDefaultLanguage());
    }

    private function dropTheRequests(): void
    {
        $stack = $this->getService(RequestStack::class);

        while (null !== $request = $stack->pop()) {
            $this->poppedRequests[] = $request;
        }

        self::assertNull($stack->getMainRequest(), 'The request stack is empty, as in a worker.');
    }

    /**
     * @return array<string, mixed>
     */
    private function parametersTheCoreSendsFor(\Thelia\Model\Order $order): array
    {
        $recordingMailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        $action = new OrderAction(
            $this->getService(RequestStack::class),
            $recordingMailer,
            $this->getService(SecurityContext::class),
            $this->getService(OrderFacade::class),
            $this->getService(GuestOrderAccessService::class),
            $this->getService(URL::class),
            $this->getService(OrderStatusTransitionGuard::class),
            $this->getService(PaymentCartContext::class),
            $this->getService(CustomerGroupPaymentPolicy::class),
        );

        $action->sendConfirmationEmail(new OrderEvent($order));

        $parameters = $recordingMailer->parametersOfMessagesSent('order_confirmation');
        self::assertCount(1, $parameters);

        return $parameters[0];
    }
}
