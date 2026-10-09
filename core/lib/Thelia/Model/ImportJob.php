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

use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Job\DataTransferJob;
use Thelia\Model\Base\ImportJob as BaseImportJob;
use Thelia\Model\Tools\DataTransferJobTrait;

class ImportJob extends BaseImportJob implements DataTransferJob
{
    use DataTransferJobTrait;

    /** What the TEXT column of the refused rows holds, with room to spare. */
    public const ROW_ERRORS_MAX_BYTES = 60000;

    /**
     * Keeps the rows the import refused, each with its reason. A reason quotes the cell
     * it refused as the file gave it, which may not be UTF-8: such bytes are replaced.
     * Thousands of refused rows do not fit the column: the first ones are kept, and a
     * last line says how many more were refused.
     *
     * @param list<string> $errors
     */
    public function setRowErrorList(array $errors): static
    {
        if ([] === $errors) {
            return $this->setRowErrors(null);
        }

        $kept = [];
        $bytes = 2;

        foreach ($errors as $index => $error) {
            $size = \strlen(self::encode([$error]));

            // Room is left for the line telling how many more there are.
            if ($bytes + $size > self::ROW_ERRORS_MAX_BYTES - 200) {
                $kept[] = Translator::getInstance()->trans('%count more rows were refused.', ['%count' => \count($errors) - $index]);

                break;
            }

            $kept[] = $error;
            $bytes += $size;
        }

        return $this->setRowErrors(self::encode($kept));
    }

    /**
     * @param list<string> $errors
     */
    private static function encode(array $errors): string
    {
        return json_encode($errors, \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR);
    }

    /**
     * The rows the import refused, each with its reason.
     *
     * @return list<string>
     */
    public function getRowErrorList(): array
    {
        try {
            $errors = json_decode((string) $this->getRowErrors(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($errors) ? array_values(array_map('strval', $errors)) : [];
    }
}
