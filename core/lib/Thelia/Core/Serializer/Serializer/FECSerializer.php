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

namespace Thelia\Core\Serializer\Serializer;

use Thelia\Core\Serializer\AbstractSerializer;

/**
 * The French accounting entries file (fichier des écritures comptables): fields separated
 * by a tab, records by CR LF, the field names on the first line, dates as YYYYMMDD and
 * amounts with a decimal comma.
 *
 * The field names are written first, so that a period without entry still gives a file the
 * accounting program accepts, and every row is written in the order of the format, a field
 * the row does not give being empty. An export gives its dates as Y-m-d and its amounts
 * with a decimal point, as every other serializer reads them. Nothing is
 * guarded against spreadsheets: the file is read by an accounting program, which must get
 * the labels as they are.
 */
class FECSerializer extends AbstractSerializer
{
    /**
     * The fields of the format, in their order.
     */
    public const FIELDS = ['JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib', 'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise'];

    private const DATE_FIELDS = ['EcritureDate', 'PieceDate', 'DateLet', 'ValidDate'];
    private const AMOUNT_FIELDS = ['Debit', 'Credit', 'Montantdevise'];

    public function getId(): string
    {
        return 'thelia.fec';
    }

    public function getName(): string
    {
        return 'FEC (tab separated accounting entries)';
    }

    public function getExtension(): string
    {
        return 'txt';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }

    public function prepareFile(\SplFileObject $fileObject): void
    {
        $fileObject->fwrite($this->record(self::FIELDS));
    }

    public function serialize(mixed $data): string
    {
        $fields = [];

        foreach (self::FIELDS as $name) {
            $value = $data[$name] ?? '';
            $value = \is_scalar($value) ? (string) $value : '';

            if (\in_array($name, self::DATE_FIELDS, true) && 1 === preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date)) {
                $value = $date[1].$date[2].$date[3];
            } elseif (\in_array($name, self::AMOUNT_FIELDS, true)) {
                $value = str_replace('.', ',', $value);
            }

            $fields[] = $value;
        }

        return $this->record($fields);
    }

    /**
     * Rows keyed by the field names of the first line, the values as the file holds them.
     */
    public function unserialize(\SplFileObject $fileObject): array
    {
        $rows = [];
        $names = null;

        while (!$fileObject->eof()) {
            $line = rtrim((string) $fileObject->fgets(), "\r\n");

            if ('' === $line) {
                continue;
            }

            $fields = explode("\t", $line);

            if (null === $names) {
                $names = $fields;

                continue;
            }

            $rows[] = array_combine($names, array_pad(\array_slice($fields, 0, \count($names)), \count($names), ''));
        }

        return $rows;
    }

    /**
     * @param list<int|string> $fields
     */
    private function record(array $fields): string
    {
        // The separators of the format cannot appear inside a field.
        return implode("\t", array_map(static fn (int|string $field): string => strtr((string) $field, ["\t" => ' ', "\r" => ' ', "\n" => ' ']), $fields))."\r\n";
    }
}
