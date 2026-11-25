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

use ApiPlatform\State\ApiResource\Error;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thelia\Domain\Quote\Exception\QuoteException;

/**
 * Puts the reason of a refusal of the quotes in the body of the error that tells it.
 *
 * The status and the message say what went wrong in words; the reason ("quote_already_ordered",
 * "revision_stale"...) is the stable name of it that a client switches on, whatever the language of
 * the message. The exception that caused the error is the one the error handler put on the request,
 * so this answers only an error that comes from a refusal of the quote facade, and leaves every
 * other error as the API makes it.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 10])]
final class QuoteProblemNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string DONE = 'quote_problem_normalized';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Error
            && !isset($context[self::DONE])
            && null !== $this->refusal();
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = $this->normalizer->normalize($data, $format, $context + [self::DONE => true]);

        if (!\is_array($normalized)) {
            return [];
        }

        $normalized['reason'] = $this->refusal()?->reason();

        return $normalized;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        // What it answers depends on the request being handled, which no cache can know.
        return [Error::class => false];
    }

    private function refusal(): ?QuoteException
    {
        $exception = $this->requestStack->getCurrentRequest()?->attributes->get('exception');

        while ($exception instanceof \Throwable) {
            if ($exception instanceof QuoteException) {
                return $exception;
            }

            $exception = $exception->getPrevious();
        }

        return null;
    }
}
