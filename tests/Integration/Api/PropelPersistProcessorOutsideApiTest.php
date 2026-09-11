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

namespace Thelia\Tests\Integration\Api;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\OrderReturnReason as OrderReturnReasonResource;
use Thelia\Model\OrderReturnReasonQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The persist processor is the one place that knows how to turn an API resource
 * into its Propel model, and it is not only reached from an API request: a theme
 * controller, a console command or a test writes through it too.
 *
 * It used to decode the body of the current request with JSON_THROW_ON_ERROR to
 * look for resource addons, so anything that was not an API call - no request at
 * all, or a form-encoded one - died on a JsonException and left the caller to
 * redo the Propel mapping by hand.
 */
final class PropelPersistProcessorOutsideApiTest extends IntegrationTestCase
{
    #[DataProvider('callersThatCarryNoJsonBody')]
    public function testAResourceIsWritableWithNoJsonBodyToRead(?Request $request): void
    {
        $stack = $this->getService(RequestStack::class);

        while (null !== $stack->getMainRequest()) {
            $stack->pop();
        }

        if (null !== $request) {
            $stack->push($request);
        }

        $code = 'reason-'.uniqid();

        $resource = new OrderReturnReasonResource();
        $resource->code = $code;
        $resource->visible = true;
        $resource->position = 1;

        $this->getService(PropelPersistProcessor::class)->process(
            $resource,
            new Post(denormalizationContext: ['groups' => [OrderReturnReasonResource::GROUP_ADMIN_WRITE]]),
        );

        self::assertNotNull(
            OrderReturnReasonQuery::create()->findOneByCode($code, $this->getPropelConnection()),
            'The resource was not written.',
        );
    }

    /**
     * @return iterable<string, array{Request|null}>
     */
    public static function callersThatCarryNoJsonBody(): iterable
    {
        yield 'no request at all (console command, worker)' => [null];
        yield 'an empty body' => [Request::create('http://localhost/admin/returns', 'POST')];
        yield 'a form-encoded body (theme controller)' => [
            Request::create('http://localhost/admin/returns', 'POST', ['code' => 'x']),
        ];
    }
}
