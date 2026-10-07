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

use Thelia\Domain\DataTransfer\Job\DataTransferJob;
use Thelia\Model\Base\ImportJob as BaseImportJob;
use Thelia\Model\Map\ImportJobTableMap;
use Thelia\Model\Tools\DataTransferJobTrait;

class ImportJob extends BaseImportJob implements DataTransferJob
{
    use DataTransferJobTrait;

    public function tableName(): string
    {
        return ImportJobTableMap::TABLE_NAME;
    }

    /**
     * Keeps the rows the import refused, each with its reason. A reason quotes the cell
     * it refused as the file gave it, which may not be UTF-8: such bytes are replaced.
     *
     * @param list<string> $errors
     */
    public function setRowErrorList(array $errors): static
    {
        return $this->setRowErrors([] === $errors ? null : json_encode($errors, \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR));
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
