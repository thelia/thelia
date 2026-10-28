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

namespace Thelia\Model;

use Thelia\Model\Base\AiUsageSetting as BaseAiUsageSetting;

/**
 * Skeleton subclass for representing a row from the 'ai_usage_setting' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class AiUsageSetting extends BaseAiUsageSetting
{
    /**
     * The optional guardrails of the usage, guardrail name to mode; empty when none is chosen or the stored text is not a JSON object.
     *
     * @return array<string, string>
     */
    public function getGuardrailMap(): array
    {
        try {
            $map = json_decode((string) $this->getGuardrails(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($map) ? array_filter($map, \is_string(...)) : [];
    }

    /**
     * @param array<string, string> $map
     */
    public function setGuardrailMap(array $map): static
    {
        return $this->setGuardrails([] === $map ? null : json_encode($map, \JSON_THROW_ON_ERROR));
    }
}
