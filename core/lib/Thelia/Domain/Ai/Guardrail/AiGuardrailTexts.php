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

namespace Thelia\Domain\Ai\Guardrail;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiMessageRole;
use Thelia\Domain\Ai\AiRequest;
use Thelia\Domain\Ai\UntrustedSource;

/**
 * What a classifier reads in a request: what a person typed (the user message they just sent and the contents the
 * module marks as typed by a visitor). The system prompt, the tool results and the pages read on the web are the
 * module's concern, not a person's.
 *
 * @internal
 */
#[Exclude]
final class AiGuardrailTexts
{
    private function __construct()
    {
    }

    /**
     * Nothing when the request answers a tool call: the person typed their message on an earlier turn, which was
     * checked then, and checking it again at every turn of a tool loop would pay for the same verdict.
     *
     * @return list<string>
     */
    public static function typedBy(AiRequest $request): array
    {
        $last = $request->messages[array_key_last($request->messages)];

        if (AiMessageRole::User !== $last->role) {
            return [];
        }

        $texts = [$last->content];

        foreach ($request->untrustedContents as $content) {
            if (UntrustedSource::VisitorInput === $content->source) {
                $texts[] = $content->content;
            }
        }

        return $texts;
    }
}
