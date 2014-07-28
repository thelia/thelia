<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tools;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileException;

/**
 * Class MimeTypeTools
 * @package Thelia\Tools
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class MimeTypeTools
{
    const TYPES_FILE = "local/config/mime.types";

    const TYPE_UNKNOWN = 0;
    const TYPE_NOT_MATCH = 1;
    const TYPE_MATCH = 2;

    protected static $instance;

    protected static $typesCache;

    /**
     * @return $this
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param $mimeType
     * @return array|bool
     */
    public function guessExtensionsFromMimeType($mimeType)
    {
        if (null === static::$typesCache) {
            static::$typesCache = $this->parseFile();
        }

        if (!is_scalar($mimeType) || !isset(static::$typesCache[$mimeType])) {
            return false;
        }

        return static::$typesCache[$mimeType];
    }

    /**
     * @param $mimeType
     * @param $fileName
     * @return bool
     */
    public function validateMimeTypeExtension($mimeType, $fileName)
    {
        $mimeType = strtolower($mimeType);

        $extensions = $this->guessExtensionsFromMimeType($mimeType);

        if (false === $extensions || !is_scalar($fileName)) {
            return static::TYPE_UNKNOWN;
        }

        $oneMatch = true;
        foreach ($extensions as $extension) {
            $oneMatch &= !!preg_match("#\.$extension$#i", $fileName);
        }

        return (bool) $oneMatch ? static::TYPE_MATCH : static::TYPE_NOT_MATCH;
    }

    /**
     * @param  null                            $filePath
     * @return array
     * @throws \Thelia\Exception\FileException
     */
    public function parseFile($filePath = null)
    {
        if (null === $filePath) {
            $filePath = THELIA_ROOT . static::TYPES_FILE;
        }

        $fileHandle = @fopen($filePath, "r");

        if ($fileHandle === false) {
            throw new FileException(
                Translator::getInstance()->trans(
                    "The file %file could not be opened",
                    [
                        "%file" => $filePath,
                    ]
                )
            );
        }

        $typesArray = [];

        while (false !== $line = fgets($fileHandle)) {
            $line = $this->realTrim($line);

            $line = preg_replace("#\#.*$#", "", $line);

            $table = explode(" ", $line);

            $mime = array_shift($table);

            if (!empty($table) && !empty($mime)) {
                $typesArray[$mime] = $table;
            }
        }

        if (!feof($fileHandle)) {
            throw new FileException(
                Translator::getInstance()->trans(
                    "An error occurred while reading the file %file",
                    [
                        "%file" => $filePath,
                    ]
                )
            );
        }

        return $typesArray;
    }

    /**
     * @param $string
     * @param  string       $characterMask
     * @return mixed|string
     */
    public function realTrim($string, $characterMask = "\t\n\r ")
    {
        $string = trim($string, $characterMask);
        $charLen = strlen($characterMask);

        $string = preg_replace(
            "#[$characterMask]+#",
            " ",
            $string
        );

        return $string;
    }
}
