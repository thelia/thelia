<?php

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

/**
 * Class AbstractSerializer.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractSerializer implements SerializerInterface
{
    public function prepareFile(\SplFileObject $fileObject)
    {
    }

    public function separator()
    {
    }

    public function finalizeFile(\SplFileObject $fileObject)
    {
    }
}
