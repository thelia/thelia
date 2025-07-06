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

use SplFileObject;
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

    public function prepareFile(SplFileObject $fileObject): void
    {
        $fileObject->fwrite('[');
    }

    public function serialize($data)
    {
        return json_encode($data);
    }

    public function separator(): string
    {
        return ','.\PHP_EOL;
    }

    public function finalizeFile(SplFileObject $fileObject): void
    {
        $fileObject->fwrite(']');
    }

    public function unserialize(SplFileObject $fileObject): mixed
    {
        return json_decode(file_get_contents($fileObject->getPathname()), true);
    }
}
