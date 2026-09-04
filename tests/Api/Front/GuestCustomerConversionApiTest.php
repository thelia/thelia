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

namespace Thelia\Tests\Api\Front;

use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * Turning the guest account into a real one.
 *
 * The account carries orders, so setting a password on it is as good as taking it
 * over. Two things prove entitlement and nothing else does: the guest token issued at
 * checkout, or a tracking token for one of the orders on the account. Knowing the
 * address is never one of them.
 */
final class GuestCustomerConversionApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    public function testAGuestCompletesItsOwnAccountWithTheTokenItHolds(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $response = $this->convert($guest['id'], ['password' => 'a-chosen-password'], $guest['token']);

        self::assertJsonResponseSuccessful($response);

        $completed = CustomerQuery::create()->findPk($guest['id'], $this->getPropelConnection());

        self::assertNotNull($completed);
        self::assertNotEmpty($completed->getPassword(), 'The account now has the password its owner chose.');
        self::assertTrue(
            $completed->isGuest(),
            'Choosing a password proves nothing yet: the row is a guest row until the code is answered.',
        );
    }

    /**
     * The guest row is shared by everyone who ever ordered on that address, so setting a
     * password on it must not be enough to reach the orders already there: the account
     * opens on the activation code, which only the mailbox it names receives.
     */
    public function testTheCompletedAccountOnlyOpensOnceItsCodeIsAnswered(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();
        $this->convert($guest['id'], ['password' => 'a-chosen-password'], $guest['token']);

        self::assertSame(
            401,
            $this->login($guest['email'], 'a-chosen-password')->getStatusCode(),
            'The chosen password alone must not open an account still waiting for its code.',
        );

        $this->answerTheActivationCode($guest['id']);

        self::assertSame(
            200,
            $this->login($guest['email'], 'a-chosen-password')->getStatusCode(),
            'Answering the code is what turns it into an account somebody can sign into.',
        );
    }

    private function login(string $email, string $password): \Symfony\Component\HttpFoundation\Response
    {
        $this->client->request(
            'POST',
            '/api/front/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(
                ['username' => $email, 'password' => $password],
                \JSON_THROW_ON_ERROR,
            ),
        );

        return $this->client->getResponse();
    }

    /**
     * What the activation page does once the code typed into it matches: the code itself
     * is mailed, and this test is about what the account does before and after, not about
     * the mail carrying it — {@see \Thelia\Tests\Integration\Domain\Customer\CustomerGuestConversionServiceTest}
     * covers that.
     */
    private function answerTheActivationCode(int $customerId): void
    {
        $customer = CustomerQuery::create()->findPk($customerId, $this->getPropelConnection());

        self::assertNotNull($customer);
        self::assertNotNull($customer->getConfirmationToken(), 'The completed account must be waiting for a code.');

        // The same two writes CustomerCodeManager::activateCustomerByCode() performs
        // once the code matches: the row is opened and stops being a guest row.
        $customer
            ->setConfirmationToken(null)
            ->setConfirmationTokenExpiresAt(null)
            ->setIsGuest(0)
            ->setEnable(1)
            ->save($this->getPropelConnection());
    }

    public function testNoTokenAtAllCompletesNothing(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $response = $this->convert($guest['id'], ['password' => 'a-chosen-password']);

        self::assertSame(403, $response->getStatusCode(), 'Knowing the customer id must not be enough to set a password.');

        $untouched = CustomerQuery::create()->findPk($guest['id'], $this->getPropelConnection());

        self::assertNotNull($untouched);
        self::assertTrue($untouched->isGuest(), 'The refused request must have changed nothing.');
        self::assertEmpty($untouched->getPassword());
    }

    public function testAnotherGuestCannotCompleteSomeoneElsesAccount(): void
    {
        $this->enableGuestCheckout();

        [, $victim] = $this->registerGuestInAFreshSession();
        [, $attacker] = $this->registerGuestInAFreshSession();

        $response = $this->convert($victim['id'], ['password' => 'taken-over'], $attacker['token']);

        self::assertSame(403, $response->getStatusCode());

        $untouched = CustomerQuery::create()->findPk($victim['id'], $this->getPropelConnection());

        self::assertNotNull($untouched);
        self::assertTrue($untouched->isGuest());
        self::assertEmpty($untouched->getPassword(), 'The attacker must not have set a password on the victim account.');
    }

    /**
     * The buyer chose a password and never opened the mail. Nothing was proved by the
     * first attempt, so the second one is not a conflict — it replaces the password and
     * mails a fresh code, and neither password opens anything until one is answered.
     */
    public function testAGuestThatNeverAnsweredItsCodeMayChooseAnotherPassword(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $this->convert($guest['id'], ['password' => 'a-chosen-password'], $guest['token']);
        $again = $this->convert($guest['id'], ['password' => 'a-second-password'], $guest['token']);

        self::assertJsonResponseSuccessful($again);

        $stored = CustomerQuery::create()->findPk($guest['id'], $this->getPropelConnection());

        self::assertNotNull($stored);
        self::assertTrue($stored->checkPassword('a-second-password'), 'The last password chosen is the one kept.');
        self::assertTrue($stored->isGuest(), 'Still waiting for a code, so still a guest row.');
    }

    /**
     * Once the code has been answered the row is an account, and the guest token that
     * completed it is not a way back in: setting a password on it again would be a reset
     * without any of a reset's guarantees.
     */
    public function testAnAccountThatAnsweredItsCodeCannotBeCompletedAgain(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $this->convert($guest['id'], ['password' => 'a-chosen-password'], $guest['token']);
        $this->answerTheActivationCode($guest['id']);

        $again = $this->convert($guest['id'], ['password' => 'a-second-password'], $guest['token']);

        self::assertSame(
            409,
            $again->getStatusCode(),
            'The token stays a guest token, and the account behind it is no longer a guest.',
        );

        // The kernel and this test share one Propel instance pool, and authenticating a
        // customer erases its credentials on the model in memory. Drop the pool so the
        // row is read from the database rather than from what the last request left.
        CustomerTableMap::clearInstancePool();

        $stored = CustomerQuery::create()->findPk($guest['id'], $this->getPropelConnection());

        self::assertNotNull($stored);
        self::assertTrue($stored->checkPassword('a-chosen-password'), 'The password of the opened account must stand.');
        self::assertFalse($stored->checkPassword('a-second-password'), 'The refused attempt must have written nothing.');
    }

    public function testAPasswordIsRequired(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        // An absent body never reaches validation — API Platform turns it away first.
        self::assertSame(400, $this->convert($guest['id'], [], $guest['token'])->getStatusCode());
        self::assertSame(422, $this->convert($guest['id'], ['password' => ''], $guest['token'])->getStatusCode());
        self::assertSame(422, $this->convert($guest['id'], ['password' => '  '], $guest['token'])->getStatusCode());

        $untouched = CustomerQuery::create()->findPk($guest['id'], $this->getPropelConnection());

        self::assertNotNull($untouched);
        self::assertTrue($untouched->isGuest(), 'None of the refused attempts may have completed the account.');
    }

    public function testAnOrderTrackingTokenAlsoProvesEntitlement(): void
    {
        $factory = $this->createFixtureFactory();
        $guest = $factory->guestCustomer($factory->customerTitle());
        $order = $factory->order($guest);
        $orderToken = $this->getService(GuestOrderAccessService::class)->createToken($order);

        $response = $this->convert($guest->getId(), [
            'password' => 'from-the-tracking-link',
            'orderToken' => $orderToken,
        ]);

        self::assertJsonResponseSuccessful($response);

        $completed = CustomerQuery::create()->findPk($guest->getId(), $this->getPropelConnection());

        self::assertNotNull($completed);
        self::assertNotEmpty($completed->getPassword(), 'The tracking link is entitlement enough to choose the password.');
        self::assertTrue($completed->isGuest(), 'And the activation code is still what opens the account.');
    }

    public function testATrackingTokenForSomebodyElsesOrderProvesNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $victim = $factory->guestCustomer($title);
        $attacker = $factory->guestCustomer($title);
        $attackerOrder = $factory->order($attacker);
        $attackerToken = $this->getService(GuestOrderAccessService::class)->createToken($attackerOrder);

        $response = $this->convert($victim->getId(), [
            'password' => 'taken-over',
            'orderToken' => $attackerToken,
        ]);

        self::assertSame(403, $response->getStatusCode());

        $untouched = CustomerQuery::create()->findPk($victim->getId(), $this->getPropelConnection());

        self::assertNotNull($untouched);
        self::assertTrue($untouched->isGuest());
        self::assertEmpty($untouched->getPassword());
    }

    public function testAForgedTrackingTokenProvesNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $guest = $factory->guestCustomer($factory->customerTitle());
        $order = $factory->order($guest);

        $response = $this->convert($guest->getId(), [
            'password' => 'taken-over',
            'orderToken' => $order->getId().'.'.(time() + 3600).'.'.str_repeat('a', 64),
        ]);

        self::assertSame(403, $response->getStatusCode());

        $untouched = CustomerQuery::create()->findPk($guest->getId(), $this->getPropelConnection());

        self::assertNotNull($untouched);
        self::assertTrue($untouched->isGuest());
    }

    public function testAnAddressTakenByARealAccountMeanwhileBlocksTheCompletion(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $factory = $this->createFixtureFactory();
        $factory->customer($factory->customerTitle(), ['email' => $guest['email']]);

        $response = $this->convert($guest['id'], ['password' => 'a-chosen-password'], $guest['token']);

        self::assertSame(
            409,
            $response->getStatusCode(),
            'Two accounts must not end up sharing one address: the guest is sent to the account that took it.',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function convert(int $customerId, array $payload, ?string $token = null): \Symfony\Component\HttpFoundation\Response
    {
        return $this->jsonRequest(
            'POST',
            '/api/front/guest-customers/'.$customerId.'/convert',
            $payload,
            $token,
        );
    }
}
