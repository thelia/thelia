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

use Thelia\Model\Base\AiConnection as BaseAiConnection;

/**
 * Skeleton subclass for representing a row from the 'ai_connection' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class AiConnection extends BaseAiConnection
{
    /**
     * Whether a key can be found: one stored encrypted, or one in the environment variable the connection names.
     * Says nothing of the key itself.
     */
    public function getHasKey(): bool
    {
        if (null !== $this->getApiKeyEncrypted() && '' !== $this->getApiKeyEncrypted()) {
            return true;
        }

        $name = (string) $this->getApiKeyEnv();

        if ('' === $name) {
            return false;
        }

        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value);
    }
}
