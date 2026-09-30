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

namespace Thelia\Module\Exception;

/**
 * A module.xml declares something the shop refuses to act on: a value it cannot read, an
 * element the module schema rejects. The install entry points catch it to stop with a
 * readable message instead of a trace.
 */
final class InvalidModuleDescriptorException extends \InvalidArgumentException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        // The message quotes a module.xml and its directory name: whoever prints it, no
        // control character of theirs reaches the operator's terminal.
        parent::__construct(self::terminalSafe($message), $code, $previous);
    }

    /**
     * The text with every control character but the tab and the line feed replaced, for a
     * message built from a module.xml or a module directory name.
     */
    public static function terminalSafe(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '?', $text) ?? '';
    }
}
