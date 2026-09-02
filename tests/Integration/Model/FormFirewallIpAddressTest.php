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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Form\ContactForm;
use Thelia\Model\FormFirewall;
use Thelia\Model\FormFirewallQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The form firewall counts the attempts of a client by its IP address, so the address
 * it writes down has to be the address it looks that client up by on the next attempt.
 * An IPv6 client announces up to 45 characters, and a column too narrow for them either
 * refuses the write or keeps a prefix no lookup matches again — either way the firewall
 * stops counting anything for every visitor reaching the shop over IPv6.
 */
final class FormFirewallIpAddressTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function clientAddressProvider(): iterable
    {
        yield 'IPv4' => ['203.0.113.7'];
        yield 'IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        yield 'IPv4-mapped IPv6, the longest address there is' => ['0000:0000:0000:0000:0000:ffff:255.255.255.255'];
    }

    #[DataProvider('clientAddressProvider')]
    public function testAnAttemptIsFoundAgainByTheAddressItWasRecordedFor(string $clientAddress): void
    {
        (new FormFirewall())
            ->setFormName(ContactForm::getName())
            ->setIpAddress($clientAddress)
            ->setAttempts(1)
            ->save();

        $recorded = FormFirewallQuery::create()
            ->filterByFormName(ContactForm::getName())
            ->filterByIpAddress($clientAddress)
            ->findOne();

        self::assertNotNull($recorded, 'The firewall must find the attempt it recorded for this client.');
        self::assertSame($clientAddress, $recorded->getIpAddress());
    }
}
