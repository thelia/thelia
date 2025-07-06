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
namespace Thelia\Core\Serializer;

use SplFileObject;

/**
 * Class AbstractSerializer.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractSerializer implements SerializerInterface
{
    public function prepareFile(SplFileObject $fileObject): void
    {
    }

    /**
     * @return string
     */
    public function separator()
    {
        return '';
    }

    public function finalizeFile(SplFileObject $fileObject): void
    {
    }
}
