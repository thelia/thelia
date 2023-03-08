<?php

declare(strict_types=1);

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

abstract class AbstractFilter implements FilterInterface
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null, protected ?array $properties = null, protected ?NameConverterInterface $nameConverter = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        foreach ($context['filters'] as $property => $value) {
            $this->filterProperty($this->denormalizePropertyName($property), $value, $query, $resourceClass, $operation, $context);
        }
    }

    /**
     * Passes a property through the filter.
     */
    abstract protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void;

    protected function getProperties(): ?array
    {
        return $this->properties;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Determines whether the given property is enabled.
     */
    protected function isPropertyEnabled(string $property, string $resourceClass): bool
    {
        if (null === $this->properties) {
            return false;
        }

        return \array_key_exists($property, $this->properties);
    }

    protected function denormalizePropertyName(string|int $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map($this->nameConverter->denormalize(...), explode('.', (string) $property)));
    }

    protected function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map($this->nameConverter->normalize(...), explode('.', $property)));
    }
}
