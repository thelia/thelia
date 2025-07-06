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

use Exception;
use Thelia\Core\Translation\Translator;

/**
 * Class SerializerManager.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class SerializerManager
{
    /**
     * @var array List of handled serializers
     */
    protected $serializers = [];

    /**
     * Reset manager.
     *
     * @return $this Return $this, allow chaining
     */
    public function reset(): self
    {
        $this->serializers = [];

        return $this;
    }

    /**
     * Get all serializers.
     *
     * @return array All serializers
     */
    public function getSerializers()
    {
        return $this->serializers;
    }

    /**
     * Determine if a serializer exists under the given identifier.
     *
     * @param string $serializerId   A serializer identifier
     * @param bool   $throwException Throw exception if serializer doesn't exists or not
     *
     * @throws \InvalidArgumentException if the serializer identifier does not exist
     *
     * @return bool True if the serializer exists, false otherwise
     */
    public function has(string $serializerId, bool $throwException = false)
    {
        $exists = isset($this->serializers[$serializerId]);

        if (!$exists && $throwException) {
            throw new \InvalidArgumentException(
                Translator::getInstance()->trans(
                    'The serializer identifier "%serializerId" doesn\’t exist',
                    [
                        '%serializerId' => $serializerId,
                    ]
                )
            );
        }

        return $exists;
    }

    /**
     * Get a serializer.
     *
     * @param string $serializerId A serializer identifier
     *
     * @return SerializerInterface Return a serializer
     */
    public function get(string $serializerId)
    {
        $this->has($serializerId, true);

        return $this->serializers[$serializerId];
    }

    /**
     * Set serializers.
     *
     * @param array $serializers An array of serializer
     *
     * @throws \Exception
     *
     * @return $this Return $this, allow chaining
     */
    public function setSerializers(array $serializers): self
    {
        $this->serializers = [];

        foreach ($serializers as $serializer) {
            if (!($serializer instanceof SerializerInterface)) {
                throw new \Exception('SerializerManager manage only '.__NAMESPACE__.'\\SerializerInterface');
            }

            $this->serializers[$serializer->getId()] = $serializer;
        }

        return $this;
    }

    /**
     * Add a serializer.
     *
     * @param SerializerInterface $serializer A serializer
     *
     * @return $this Return $this, allow chaining
     */
    public function add(SerializerInterface $serializer): self
    {
        $this->serializers[$serializer->getId()] = $serializer;

        return $this;
    }

    /**
     * Remove a serializer.
     *
     * @param string $serializerId A serializer identifier
     */
    public function remove(string $serializerId): void
    {
        $this->has($serializerId, true);

        unset($this->serializers[$serializerId]);
    }
}
