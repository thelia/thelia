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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Core\HttpFoundation\Session\SessionFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A visitor keeps one session across the requests he sends, and a form whose token lives
 * in that session is therefore submittable. Everything a page hands to the visitor to send
 * back — a CSRF token, a cart, a flash message — depends on it.
 */
final class SessionRoundTripTest extends WebIntegrationTestCase
{
    public function testTheSessionOpenedByTheFirstRequestIsResumedByTheNextOne(): void
    {
        $this->assertPageRenders('/contact');
        $firstSessionId = $this->sessionIdHeldByTheClient();

        $this->assertPageRenders('/contact');
        $secondSessionId = $this->sessionIdHeldByTheClient();

        self::assertNotNull($firstSessionId, 'The first request must hand a session cookie to the client.');
        self::assertSame(
            $firstSessionId,
            $secondSessionId,
            'The second request must resume the session the client was handed, not open a new one.',
        );
    }

    public function testAFormWhoseTokenLivesInTheSessionCanBeSubmittedBack(): void
    {
        $this->assertPageRenders('/contact');

        $form = $this->client->getCrawler()->filter('form[name="thelia_contact"]')->form([
            'thelia_contact[name]' => 'Jane Doe',
            'thelia_contact[email]' => 'jane.doe@example.com',
            'thelia_contact[subject]' => 'A question',
            'thelia_contact[message]' => 'Is anybody out there?',
        ]);

        $this->client->submit($form);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'The submission must be accepted: a rejected token serves the page again with a 200.',
        );
    }

    private function sessionIdHeldByTheClient(): ?string
    {
        $sessionName = $this->getService(SessionFactory::class)->createSession()->getName();

        return $this->client->getCookieJar()->get($sessionName)?->getValue();
    }
}
