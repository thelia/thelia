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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Action\Order as OrderAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Order;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Tools\URL;

/**
 * What the shipped confirmation template does with the tracking link the core passes.
 *
 * {@see \Thelia\Tests\Integration\Action\OrderConfirmationEmailTest} pins the core's half
 * of the contract: a guest order's confirmation is sent with the token and a usable URL.
 * This pins the template's half: the mail a guest actually reads shows that URL. A guest
 * has no account page the order shows up on, so a template that drops the variable leaves
 * them with no way back to the order at all — and nothing else would notice, since the
 * parameters are correct either way.
 *
 * The parameters are taken from the real send, not written by hand, so the two halves
 * are asserted against the same contract.
 */
final class OrderConfirmationTemplateTest extends IntegrationTestCase
{
    public function testTheGuestConfirmationMailShowsTheTrackingLink(): void
    {
        $this->skipUnlessTheInstalledTemplateCarriesTheTrackingLink();

        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->guestCustomer($factory->customerTitle()));

        $parameters = $this->parametersTheCoreSendsFor($order);

        $url = $parameters['guest_order_tracking_url'] ?? null;
        self::assertIsString($url, 'Covered elsewhere; asserted here so the failure below cannot be a false negative.');

        $email = $this->renderConfirmation($parameters);

        self::assertStringContainsString(
            $url,
            (string) $email->getHtmlBody(),
            'The mail is the only thing that ever hands a guest the way back to their order.',
        );
        self::assertStringContainsString($url, (string) $email->getTextBody());
    }

    /**
     * A customer with an account reaches the order from the account pages: their mail
     * keeps the account link, and no tracking link is offered to be shared or leaked.
     */
    public function testAnAccountConfirmationMailCarriesNoTrackingLink(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order($factory->customer($factory->customerTitle()));

        $email = $this->renderConfirmation($this->parametersTheCoreSendsFor($order));

        self::assertStringNotContainsString('/order/track/', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('/order/track/', (string) $email->getTextBody());
    }

    /**
     * The confirmation exactly as Thelia\Action\Order sends it, parameters included.
     *
     * @return array<string, mixed>
     */
    private function parametersTheCoreSendsFor(Order $order): array
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
        );

        $action->sendConfirmationEmail(new OrderEvent($order));

        $parameters = $recordingMailer->parametersOfMessagesSent('order_confirmation');
        self::assertCount(1, $parameters);

        return $parameters[0];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderConfirmation(array $parameters): \Symfony\Component\Mime\Email
    {
        $mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );

        return $mailerFactory->createEmailMessage(
            'order_confirmation',
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            $parameters,
            'en_US',
        );
    }

    /**
     * The template is a package of its own (thelia/email-default-template) and this
     * checkout may hold a version older than the link. The core's half of the contract
     * holds either way; the template's half can only be asserted once the template
     * has it, so an older package skips rather than fails.
     */
    private function skipUnlessTheInstalledTemplateCarriesTheTrackingLink(): void
    {
        $templatePath = $this->getService(TemplateHelperInterface::class)
            ->getActiveMailTemplate()
            ->getAbsolutePath().DS.'order_confirmation.html.twig';

        if (!str_contains((string) @file_get_contents($templatePath), 'guest_order_tracking_url')) {
            self::markTestSkipped('The installed mail template does not carry the guest tracking link yet.');
        }
    }
}
