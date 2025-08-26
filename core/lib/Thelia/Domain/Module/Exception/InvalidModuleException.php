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

namespace Thelia\Domain\Module\Exception;

/**
 * Class InvalidModuleException.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class InvalidModuleException extends \RuntimeException
{
    public function __construct(protected array $errors = [])
    {
        parent::__construct();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsAsString(string $separator = "\n"): string
    {
        $message = '';

        /** @var \Exception $error */
        foreach ($this->errors as $error) {
            $message .= $error->getMessage().$separator;
        }

        return rtrim($message, $separator);
    }

    public function __toString(): string
    {
        return \sprintf(
            'InvalidModuleException: %s',
            $this->getErrorsAsString(),
        );
    }
}
