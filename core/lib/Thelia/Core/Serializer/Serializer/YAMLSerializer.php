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
use Symfony\Component\Yaml\Yaml;
use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class YAMLSerializer.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class YAMLSerializer extends AbstractSerializer
{
    public function getId(): string
    {
        return 'thelia.yml';
    }

    public function getName(): string
    {
        return 'YAML';
    }

    public function getExtension(): string
    {
        return 'yaml';
    }

    public function getMimeType(): string
    {
        return 'application/x-yaml';
    }

    public function serialize($data): string
    {
        return Yaml::dump([$data]);
    }

    public function unserialize(SplFileObject $fileObject): mixed
    {
        return Yaml::parse(file_get_contents($fileObject->getPathname()));
    }
}
