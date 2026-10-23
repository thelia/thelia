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

namespace Thelia\Api\Serializer;

use ApiPlatform\Metadata\Exception\ExceptionInterface as ApiPlatformExceptionInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Thelia\Api\Resource\OrderReturn;
use Thelia\Api\Resource\OrderReturnLine;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;

/**
 * Gives a customer who names an order, or an order product, that does not exist
 * the refusal the return processor gives one that is not theirs: same status,
 * same message.
 *
 * API Platform resolves the IRIs of a body while it denormalizes it, before the
 * processor runs, and refuses an IRI that points at nothing with its own 400.
 * The processor refuses somebody else's order with a 422 and its own message, so
 * the two cases of this request told each other apart by their status. The
 * refusal is now raised here, with the processor's words, as soon as the
 * relation that failed is one of those two.
 *
 * That is all it promises, within this request. It does not hide which orders
 * exist: `GET /api/front/account/orders/{id}` (403 for somebody else's order,
 * 404 for a missing one), an IRI of another surface and some malformed bodies
 * (an empty line, the order of the keys) still answer differently. With
 * `collect_denormalization_errors` the serializer would gather the failures of
 * the properties itself, and this denormalizer would no longer see the missing
 * item it rewrites.
 *
 * Only the customer's write group is concerned. The administration names real
 * rows, and a precise "not found" is what it needs.
 *
 * It runs after PlainIdentifierDenormalizer (priority 0), which turns a bare
 * identifier into an IRI: this one then only sees IRIs.
 *
 * It also keeps the lines of the return new. API Platform reads a line given
 * as an IRI, as an object carrying an `@id` (JSON-LD) or an `id` (JSON), from
 * the database and writes the body onto it: the return being opened would take
 * over that line, whichever return and customer it belonged to. Such a line is
 * refused here, before anything is read, with the same answer whether the line
 * exists or not.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => -10])]
final class FrontOrderReturnRelationDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const EXISTING_LINE_MESSAGE = 'A return line must be a new line, not an existing one.';

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (null === self::relationOf($type)
            || !\in_array(OrderReturn::GROUP_FRONT_WRITE, (array) ($context['groups'] ?? []), true)
            || isset($context[self::guardOf($type)])) {
            return false;
        }

        // A line that is not an object (an IRI, a bare identifier) is taken in
        // too, to be refused: it can only name an existing line.
        return \is_array($data) || OrderReturnLine::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            OrderReturn::class => false,
            OrderReturnLine::class => false,
        ];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (OrderReturnLine::class === $type && self::namesAnExistingLine($data)) {
            throw new UnprocessableEntityHttpException(self::EXISTING_LINE_MESSAGE);
        }

        try {
            // One guard per type: the line is denormalized from inside the
            // return, with the context of the return.
            return $this->denormalizer->denormalize($data, $type, $format, $context + [self::guardOf($type) => true]);
        } catch (ExceptionInterface $exception) {
            [$property, $message] = self::relationOf($type) ?? [null, null];

            if (null === $property || !self::isMissingItem($exception) || $this->resolves($data[$property] ?? null)) {
                throw $exception;
            }

            throw new UnprocessableEntityHttpException($message, $exception);
        }
    }

    /**
     * Whether a line of the body points at a line already written rather than
     * describing a new one. The value is never looked up; a key set to null
     * names nothing, for API Platform as here.
     */
    private static function namesAnExistingLine(mixed $data): bool
    {
        return !\is_array($data) || isset($data['@id']) || isset($data['id']);
    }

    /**
     * The property of the type whose missing IRI is answered like a foreign one,
     * and the refusal the processor gives the foreign one.
     *
     * @return array{string, string}|null
     */
    private static function relationOf(string $type): ?array
    {
        return match ($type) {
            OrderReturn::class => ['order', ReturnEligibilityChecker::ORDER_NOT_USABLE_MESSAGE],
            OrderReturnLine::class => ['orderProduct', ReturnEligibilityChecker::ORDER_PRODUCT_NOT_USABLE_MESSAGE],
            default => null,
        };
    }

    private static function guardOf(string $type): string
    {
        return self::class.'#'.$type;
    }

    private static function isMissingItem(\Throwable $exception): bool
    {
        for ($cause = $exception; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof ItemNotFoundException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the value names something that exists. The body may have failed on
     * another relation (the reason of the return, say): only a missing order or
     * order product is rewritten, the rest keeps the answer it had.
     */
    private function resolves(mixed $value): bool
    {
        // A plain identifier is turned into an IRI by PlainIdentifierDenormalizer,
        // which runs first (priority 0 against -10 here) and calls back into this
        // one with the IRI: the check is made there.
        if (!\is_string($value) || !str_contains($value, '/')) {
            return true;
        }

        try {
            $this->iriConverter->getResourceFromIri($value, ['fetch_data' => true]);
        } catch (ItemNotFoundException) {
            return false;
        } catch (ApiPlatformExceptionInterface) {
            return true;
        }

        return true;
    }
}
