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

namespace Thelia\Core\Archiver;

use Thelia\Core\Translation\Translator;

/**
 * Class ArchiverManager.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ArchiverManager
{
    protected array $archivers = [];

    public function reset(): static
    {
        $this->archivers = [];

        return $this;
    }

    public function getArchivers(?bool $isAvailable = null): array
    {
        if (null === $isAvailable) {
            return $this->archivers;
        }

        $filteredArchivers = [];

        /** @var ArchiverInterface $archiver */
        foreach ($this->archivers as $archiver) {
            if ($archiver->isAvailable() === $isAvailable) {
                $filteredArchivers[] = $archiver;
            }
        }

        return $filteredArchivers;
    }

    /**
     * @throws \InvalidArgumentException if the archiver identifier does not exist
     */
    public function has(string $archiverId, bool $throwException = false): bool
    {
        $exists = isset($this->archivers[$archiverId]);

        if (!$exists && $throwException) {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('The archiver identifier "%archiverId" doesn\’t exist', ['%archiverId' => $archiverId]));
        }

        return $exists;
    }

    public function get(string $archiverId, ?bool $isAvailable = null): ?ArchiverInterface
    {
        $this->has($archiverId, true);

        if (null === $isAvailable) {
            return $this->archivers[$archiverId];
        }

        if ($this->archivers[$archiverId]->isAvailable() === $isAvailable) {
            return $this->archivers[$archiverId];
        }

        return null;
    }

    public function setArchivers(array $archivers): static
    {
        $this->archivers = [];

        foreach ($archivers as $archiver) {
            if (!$archiver instanceof ArchiverInterface) {
                throw new \Exception('ArchiverManager manage only '.__NAMESPACE__.'\\ArchiverInterface');
            }

            $this->archivers[$archiver->getId()] = $archiver;
        }

        return $this;
    }

    public function add(ArchiverInterface $archiver): static
    {
        $this->archivers[$archiver->getId()] = $archiver;

        return $this;
    }

    public function remove(string $archiverId): void
    {
        $this->has($archiverId, true);

        unset($this->archivers[$archiverId]);
    }
}
