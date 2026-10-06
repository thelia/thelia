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

use Thelia\Domain\DataTransfer\Job\ImportJobLauncher;
use Thelia\Model\Base\ImportJob as BaseImportJob;
use Thelia\Model\Tools\DataTransferJobTrait;

class ImportJob extends BaseImportJob
{
    use DataTransferJobTrait;

    /**
     * Where the uploaded file is on this server. The row keeps a path relative to the
     * project, so it stays short and survives the project being moved.
     */
    public function getStoredFilePath(): string
    {
        $path = (string) $this->getFilePath();

        return str_starts_with($path, '/') ? $path : THELIA_ROOT.$path;
    }

    /**
     * Whether the stored path points inside the import storage of this project: the
     * only place a job's file may be deleted from.
     */
    public function isStoredInTheImportDirectory(): bool
    {
        $directory = realpath(THELIA_ROOT.ImportJobLauncher::STORAGE_DIRECTORY);
        $file = realpath($this->getStoredFilePath());

        return false !== $directory && false !== $file && str_starts_with($file, $directory.\DIRECTORY_SEPARATOR);
    }

    /**
     * The rows the import refused, each with its reason.
     *
     * @return list<string>
     */
    public function getRowErrorList(): array
    {
        $errors = json_decode((string) $this->getRowErrors(), true);

        return \is_array($errors) ? array_values(array_map('strval', $errors)) : [];
    }
}
