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

namespace Thelia\Core\Event\Hook;

/**
 * HookRenderEvent is used by the hook template engine plugin function.
 *
 * Class HookRenderEvent
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookRenderEvent extends BaseHookRenderEvent
{
    /** @var array an array of fragments collected during the event dispatch */
    protected array $fragments = [];

    public function __construct($code, array $arguments = [], array $templateVariables = [])
    {
        parent::__construct($code, $arguments, $templateVariables);
    }

    /**
     * Add a new fragment.
     *
     * @return $this
     */
    public function add(string $content): static
    {
        $this->fragments[] = $content;

        return $this;
    }

    /**
     * Get an array of all the fragments.
     */
    public function get(): array
    {
        return $this->fragments;
    }

    /**
     * Concatenates all fragments in a string.
     *
     * @param string $glue   the glue between fragments
     * @param string $before the text before the concatenated string
     * @param string $after  the text after the concatenated string
     *
     * @return string the concatenate string
     */
    public function dump(string $glue = '', string $before = '', string $after = ''): string
    {
        $ret = '';

        if ([] !== $this->fragments) {
            $ret = $before.implode($glue, $this->fragments).$after;
        }

        return $ret;
    }
}
