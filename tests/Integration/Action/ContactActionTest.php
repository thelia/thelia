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

namespace Thelia\Tests\Integration\Action;

use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Form\Form;
use Thelia\Core\Event\Contact\ContactEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Form\Definition\FrontForm;
use Thelia\Model\ConfigQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ContactActionTest extends ActionIntegrationTestCase
{
    use MailerAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // A bare install leaves the store address empty, and the listener then has no
        // recipient to write to. The transaction of the test case rolls the value back.
        ConfigQuery::create()
            ->findOneByName('store_email')
            ?->setValue('shop@example.com')
            ->save();
    }

    public function testSubmittingTheContactFormMailsTheMessageToTheStore(): void
    {
        $this->dispatch(
            new ContactEvent($this->submittedContactForm()),
            TheliaEvents::CONTACT_SUBMIT,
        );

        self::assertCount(1, $this->mailerMessages());

        $message = $this->mailerMessages()[0];

        self::assertSame('A question about an order', $message->getSubject());
        self::assertSame(
            [ConfigQuery::getStoreEmail()],
            array_map(static fn ($address): string => $address->getAddress(), $message->getTo()),
        );

        // Answering the shop's copy has to reach the visitor, who is not the sender.
        self::assertSame(
            ['jane@example.com'],
            array_map(static fn ($address): string => $address->getAddress(), $message->getReplyTo()),
        );

        self::assertStringContainsString('jane@example.com', (string) $message->getTextBody());
        self::assertStringContainsString('Where is my parcel?', (string) $message->getTextBody());
        self::assertStringContainsString('Where is my parcel?', (string) $message->getHtmlBody());
    }

    public function testTheMessageIsEscapedInTheHtmlBody(): void
    {
        $this->dispatch(
            new ContactEvent($this->submittedContactForm([
                'message' => 'Hello <script>alert(1)</script>',
            ])),
            TheliaEvents::CONTACT_SUBMIT,
        );

        $htmlBody = (string) $this->mailerMessages()[0]->getHtmlBody();

        self::assertStringNotContainsString('<script>', $htmlBody);
        self::assertStringContainsString('&lt;script&gt;', $htmlBody);
    }

    /**
     * @param array<string, string> $overrides
     */
    private function submittedContactForm(array $overrides = []): Form
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(FrontForm::CONTACT)
            ->getForm();

        $form->submit($overrides + [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A question about an order',
            'message' => 'Where is my parcel?',
        ]);

        return $form;
    }

    /**
     * @return list<\Symfony\Component\Mime\Email>
     */
    private function mailerMessages(): array
    {
        return array_values(array_filter(
            self::getMailerMessages(),
            static fn ($message): bool => $message instanceof \Symfony\Component\Mime\Email,
        ));
    }
}
