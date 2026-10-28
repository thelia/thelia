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

namespace Thelia\Tests\Unit\Domain\Ai\Tool;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiActor;
use Thelia\Domain\Ai\AiMessageRole;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiChangeProposal;
use Thelia\Domain\Ai\Tool\AiToolCallStatus;
use Thelia\Domain\Ai\Tool\AiToolChannel;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiToolResult;
use Thelia\Domain\Ai\Tool\Internal\AiToolSchema;

final class AiToolValueObjectsTest extends TestCase
{
    public function testThePublicConstructorBuildsAContextOfTheGateway(): void
    {
        $context = new AiToolContext(AiActor::admin(1), 'shop.assistant', 'shop.conversation.1', 'fr_FR');

        self::assertSame(AiToolChannel::Gateway, $context->channel);
        self::assertNull($context->tokenId);
        self::assertNull($context->offeredTools);
        self::assertFalse($context->isOffered('shop_read'));
    }

    public function testTheListOfTheOfferIsStampedOnACopyAndTheOriginalKeepsNone(): void
    {
        $context = new AiToolContext(AiActor::admin(1), 'shop.assistant');
        $offered = $context->withOfferedTools(['shop_read', 'shop_read', 'shop_write']);

        self::assertSame(['shop_read', 'shop_write'], $offered->offeredTools);
        self::assertTrue($offered->isOffered('shop_write'));
        self::assertFalse($offered->isOffered('shop_other'));
        self::assertNull($context->offeredTools);
        self::assertSame($context->actor, $offered->actor);
        self::assertSame('en_US', $offered->locale);
    }

    public function testOnlyTheMcpFactoryBuildsAContextOfTheMcpChannel(): void
    {
        $context = AiToolContext::forMcp(AiActor::admin(7), 3, 'fr_FR');

        self::assertSame(AiToolChannel::Mcp, $context->channel);
        self::assertSame(3, $context->tokenId);
        self::assertSame('fr_FR', $context->locale);
        self::assertNull($context->correlationId);
    }

    public function testAnMcpContextNeedsAStoredToken(): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        AiToolContext::forMcp(AiActor::admin(7), 0);
    }

    public function testTheContextOfTheBackOfficeCarriesTheApprover(): void
    {
        $context = AiToolContext::forBackOffice(AiActor::admin(9), 'shop.assistant', 'shop.conversation.2');

        self::assertSame(AiToolChannel::BackOffice, $context->channel);
        self::assertSame('9', $context->actor->identifier);
        self::assertSame('shop.assistant', $context->usageId);
    }

    public function testAProposalHoldsItsLimits(): void
    {
        $proposal = new AiChangeProposal('product_sale_elements:42', 'Price of MUG: 16.5 → 14.9', ['price' => '16.5'], ['price' => '14.9']);

        self::assertSame('product_sale_elements:42', $proposal->target);

        $this->expectException(InvalidAiArgumentException::class);

        new AiChangeProposal(str_repeat('a', 129), 'summary', [], []);
    }

    public function testASummaryOfMoreThan512CharactersIsRefused(): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        new AiChangeProposal('a:1', str_repeat('é', 513), [], []);
    }

    public function testAStateThatIsTooBigIsRefused(): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        new AiChangeProposal('a:1', 'summary', [], ['blob' => str_repeat('x', 40000)]);
    }

    public function testAnEmptyResultIsSentAsAnEmptyObject(): void
    {
        $message = (new AiToolResult(AiToolCallStatus::Succeeded, [], null, 1))->toMessage('toolu_01');

        self::assertSame(AiMessageRole::Tool, $message->role);
        self::assertSame('{}', $message->content);
        self::assertSame('toolu_01', $message->toolCallId);
    }

    public function testAResultIsSentAsJson(): void
    {
        $message = (new AiToolResult(AiToolCallStatus::Succeeded, ['count' => 1], null, 1))->toMessage('toolu_01');

        self::assertSame('{"count":1}', $message->content);
    }

    public function testAnEmptyPropertiesListGoesOutAsAnObject(): void
    {
        $schema = AiToolSchema::forTransport('shop_read', ['type' => 'object', 'additionalProperties' => false, 'properties' => []]);

        self::assertSame('{"type":"object","additionalProperties":false,"properties":{}}', json_encode($schema));
    }

    public function testASchemaThatAcceptsUnknownPropertiesIsRefused(): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        AiToolSchema::forTransport('shop_read', ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]]);
    }
}
