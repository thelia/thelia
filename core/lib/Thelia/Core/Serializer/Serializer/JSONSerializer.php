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
 * Class JSONSerializer.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class JSONSerializer extends AbstractSerializer
{
    public function getId(): string
    {
        return 'thelia.json';
    }

    public function getName(): string
    {
        return 'JSON';
    }

    public function getExtension(): string
    {
        return 'json';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function prepareFile(\SplFileObject $fileObject): void
    {
        $fileObject->fwrite('[');
    }

    /**
     * @throws \JsonException
     */
    public function serialize(mixed $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR);
    }

    public function separator(): string
    {
        return ','.\PHP_EOL;
    }

    public function finalizeFile(\SplFileObject $fileObject): void
    {
        $fileObject->fwrite(']');
    }

    /**
     * @throws \JsonException
     */
    public function unserialize(\SplFileObject $fileObject): array
    {
        return json_decode(file_get_contents($fileObject->getPathname()), true, 512, \JSON_THROW_ON_ERROR);
    }
}
