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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Customer\Service\CustomerGroupRegistration;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * `POST /api/front/customers`: a visitor registers an account, in the default group or, with the
 * token of a registration link, in the group the link opens. The visitor never names a group.
 */
final class CustomerRegistrationApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    private const string NEUTRAL_MESSAGE = 'This registration link is not valid.';

    private FixtureFactory $factory;
    private CustomerGroupRegistration $registration;
    private string $previousEmailConfirmation = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->registration = $this->getService(CustomerGroupRegistration::class);
        $this->previousEmailConfirmation = (string) ConfigQuery::read('customer_email_confirmation', '0');
        ConfigQuery::write('customer_email_confirmation', '0');
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('customer_email_confirmation', $this->previousEmailConfirmation);

        parent::tearDown();
    }

    public function testAVisitorRegistersInTheDefaultGroupWithoutAToken(): void
    {
        $response = $this->register(['email' => 'plain@test.com']);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame('Reg', $body['firstname']);
        self::assertSame('plain@test.com', $body['email']);
        self::assertSame(CustomerGroupQuery::findDefault()->getCode(), $body['customerGroup']['code']);
        $this->assertTheAnswerKeepsItsSecrets($body);

        $stored = $this->storedCustomer('plain@test.com');
        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $stored->getCustomerGroupId());
        self::assertTrue($stored->checkPassword('secret123'), 'The password is stored hashed and verifies.');
        self::assertNotSame('secret123', $stored->getPassword());
    }

    public function testTheTokenOfALinkPutsTheNewAccountInItsGroupAndTheLifeOfTheToken(): void
    {
        $pro = $this->factory->customerGroup('pro', discount: 15.0);
        $first = $this->registration->open($pro);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);

        // The token of the open link: the customer is in pro.
        $response = $this->register(['email' => 'pro-1@test.com', 'registrationToken' => $first]);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame('pro', $body['customerGroup']['code']);
        $this->assertTheAnswerKeepsItsSecrets($body);
        self::assertSame($pro->getId(), $this->storedCustomer('pro-1@test.com')->getCustomerGroupId());

        // Regenerated: the old token opens nothing and creates nothing, the new one opens pro.
        $second = $this->registration->regenerate($pro);
        self::assertNotSame($first, $second);

        $refused = $this->register(['email' => 'pro-2@test.com', 'registrationToken' => $first]);
        $this->assertTheTokenIsRefused($refused);
        self::assertNull($this->findCustomer('pro-2@test.com'), 'A refused token creates nothing.');

        self::assertSame(Response::HTTP_CREATED, $this->register(['email' => 'pro-3@test.com', 'registrationToken' => $second])->getStatusCode());
        self::assertSame($pro->getId(), $this->storedCustomer('pro-3@test.com')->getCustomerGroupId());

        // Closed: the last token opens nothing either.
        $this->registration->close($pro);
        $this->assertTheTokenIsRefused($this->register(['email' => 'pro-4@test.com', 'registrationToken' => $second]));
        self::assertNull($this->findCustomer('pro-4@test.com'));

        // Without a token, the default group still.
        self::assertSame(Response::HTTP_CREATED, $this->register(['email' => 'pro-5@test.com'])->getStatusCode());
        self::assertSame(CustomerGroupQuery::findDefault()->getId(), $this->storedCustomer('pro-5@test.com')->getCustomerGroupId());
    }

    public function testAnUnknownAndAClosedTokenAreAnsweredAlike(): void
    {
        $closed = $this->factory->customerGroup('was-open', openRegistration: true);
        $token = (string) $closed->getRegistrationToken();
        $this->registration->close($closed);

        $unknownResponse = $this->register(['email' => 'unknown@test.com', 'registrationToken' => str_repeat('0', 64)]);
        $closedResponse = $this->register(['email' => 'closed@test.com', 'registrationToken' => $token]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $unknownResponse->getStatusCode());
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $closedResponse->getStatusCode());
        $unknown = $this->decode($unknownResponse);
        $closedAnswer = $this->decode($closedResponse);

        self::assertNotEmpty($unknown['violations'] ?? []);
        self::assertSame($unknown['violations'], $closedAnswer['violations']);
        self::assertSame($unknown['detail'], $closedAnswer['detail']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['abc123'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'not hexadecimal' => [str_repeat('z', 64)];
        yield 'sql' => ["' OR '1'='1"];
    }

    #[DataProvider('malformedTokens')]
    public function testAMalformedTokenIsRefused(string $token): void
    {
        $this->factory->customerGroup('open-group', openRegistration: true);

        $this->assertTheTokenIsRefused($this->register(['email' => 'malformed@test.com', 'registrationToken' => $token]));
        self::assertNull($this->findCustomer('malformed@test.com'));
    }

    public function testAnUppercaseTokenOfAnOpenLinkIsRefused(): void
    {
        $group = $this->factory->customerGroup('case', openRegistration: true);

        $this->assertTheTokenIsRefused($this->register(['email' => 'case@test.com', 'registrationToken' => strtoupper((string) $group->getRegistrationToken())]));
        self::assertNull($this->findCustomer('case@test.com'));
    }

    public function testNoFieldOfTheBodyNamesAGroup(): void
    {
        $sneaky = $this->factory->customerGroup('sneaky', discount: 50.0);
        $default = CustomerGroupQuery::findDefault();

        foreach ([
            'customerGroup' => '/api/admin/customer_groups/'.$sneaky->getId(),
            'customerGroupId' => $sneaky->getId(),
            'customer_group_id' => $sneaky->getId(),
            'group' => $sneaky->getId(),
            'isDefault' => true,
        ] as $field => $value) {
            $email = 'sneaky-'.md5($field).'@test.com';
            $response = $this->register(['email' => $email, $field => $value]);

            self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $field.': '.$response->getContent());
            self::assertSame($default->getId(), $this->storedCustomer($email)->getCustomerGroupId(), $field.' must not choose the group');
        }
    }

    public function testATokenIsNotAGroupInAnotherField(): void
    {
        $pro = $this->factory->customerGroup('pro-field', openRegistration: true);
        $default = CustomerGroupQuery::findDefault();

        $this->register(['email' => 'field@test.com', 'registrationToken' => $default->getCode(), 'customerGroup' => $pro->getCode()]);
        self::assertNull($this->findCustomer('field@test.com'));
    }

    public function testTheRegistrationIsValidated(): void
    {
        $this->factory->customer($this->factory->customerTitle(), ['email' => 'taken@test.com']);

        $taken = $this->register(['email' => 'taken@test.com']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $taken->getStatusCode(), (string) $taken->getContent());
        self::assertSame('email', $this->decode($taken)['violations'][0]['propertyPath']);

        foreach ([
            'email' => ['email' => 'not-an-email'],
            'password' => ['password' => 'x'],
            'firstname' => ['firstname' => ''],
            'langId' => ['langId' => 999999],
        ] as $path => $overrides) {
            $response = $this->register($overrides + ['email' => 'invalid@test.com']);

            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $path.': '.$response->getContent());
            self::assertContains($path, array_column($this->decode($response)['violations'], 'propertyPath'));
        }

        self::assertNull($this->findCustomer('invalid@test.com'));
    }

    public function testTheConfirmationTokenOfTheAddressIsNotInTheAnswer(): void
    {
        ConfigQuery::write('customer_email_confirmation', '1');

        $response = $this->register(['email' => 'confirm@test.com']);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $stored = $this->storedCustomer('confirm@test.com');
        self::assertNotEmpty($stored->getConfirmationToken(), 'The token exists: it is mailed to the address.');
        $this->assertTheAnswerKeepsItsSecrets($this->decode($response));
        self::assertStringNotContainsString((string) $stored->getConfirmationToken(), (string) $response->getContent());
        self::assertSame(0, $stored->getEnable(), 'The account waits for the confirmation.');
    }

    public function testTheTokenIsNeverServedToTheStorefront(): void
    {
        $pro = $this->factory->customerGroup('pro-front', openRegistration: true);
        $token = (string) $pro->getRegistrationToken();
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password', 'customerGroup' => $pro]);
        $jwt = $this->authenticateAsCustomer($customer);

        $served = [
            $this->jsonRequest('GET', '/api/front/customer_groups/'.$pro->getId(), token: $jwt),
            $this->jsonRequest('GET', '/api/front/account/customers/'.$customer->getId(), token: $jwt),
            $this->jsonRequest('GET', '/api/front/customer_groups/'.$pro->getId(), format: 'json', token: $jwt),
        ];

        foreach ($served as $response) {
            self::assertSame(200, $response->getStatusCode());
            self::assertStringNotContainsString($token, (string) $response->getContent());
            self::assertStringNotContainsStringIgnoringCase('registrationToken', (string) $response->getContent());
        }

        $this->factory->customerGroup('other', openRegistration: true);
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/customer_groups/'.CustomerGroupQuery::create()->findOneByCode('other')->getId(), token: $jwt)->getStatusCode());
    }

    public function testTheOpenApiDocumentOffersTheTokenToTheRegistrationAndToTheAdministrationOnly(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 300));
        $schemas = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['components']['schemas'] ?? [];

        $carriers = array_keys(array_filter($schemas, static fn (array $schema): bool => isset($schema['properties']['registrationToken'])));
        self::assertNotEmpty($carriers);

        foreach ($carriers as $name) {
            self::assertTrue(
                str_contains($name, 'customer.register') || str_contains($name, 'customer:register') || str_contains($name, 'admin.customer_group') || str_contains($name, 'admin:customer_group'),
                \sprintf('The schema "%s" offers registrationToken, and is neither the registration body nor an administration one.', $name),
            );
            self::assertStringNotContainsString('front.customer_group', $name);
        }

        self::assertNotEmpty(array_filter($carriers, static fn (string $name): bool => str_contains($name, 'register')), 'The registration body documents the token.');
        self::assertNotEmpty(array_filter($carriers, static fn (string $name): bool => str_contains($name, 'admin')), 'The administration read documents it: the check above can fail.');
        self::assertEmpty(array_filter(
            array_keys($schemas),
            static fn (string $name): bool => str_contains($name, 'front') && str_contains($name, 'read') && isset($schemas[$name]['properties']['registrationToken']),
        ), 'No front read schema carries the token.');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function register(array $overrides): Response
    {
        return $this->jsonRequest('POST', '/api/front/customers', $overrides + [
            'firstname' => 'Reg',
            'lastname' => 'Istrant',
            'email' => 'registrant@test.com',
            'password' => 'secret123',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function assertTheTokenIsRefused(Response $response): void
    {
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        $violations = $this->decode($response)['violations'];
        self::assertCount(1, $violations);
        self::assertSame('registrationToken', $violations[0]['propertyPath']);
        self::assertSame(self::NEUTRAL_MESSAGE, $violations[0]['message']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assertTheAnswerKeepsItsSecrets(array $body): void
    {
        foreach (['password', 'registrationToken', 'confirmationToken', 'customerGroupId'] as $secret) {
            self::assertArrayNotHasKey($secret, $body, $secret.' is not in the answer');
        }

        self::assertArrayNotHasKey('registrationToken', $body['customerGroup'] ?? []);
    }

    private function findCustomer(string $email): ?Customer
    {
        CustomerTableMap::clearInstancePool();

        return CustomerQuery::create()->filterByEmail($email)->findOne();
    }

    private function storedCustomer(string $email): Customer
    {
        return $this->findCustomer($email) ?? throw new \LogicException('No customer for '.$email);
    }
}
