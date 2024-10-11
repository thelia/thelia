<?php

namespace TwigEngine\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FilterExtension extends AbstractExtension
{
    public function __construct() {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('filters_count', [$this, 'getFiltersCount']),
        ];
    }

    public function getFiltersCount(array $fitlers): int
    {
        $count = 0;

        foreach ($fitlers as $filter) {
            if (is_array($filter)) {
                $count += $this->getFiltersCount($filter);
            } else {
                if (!empty($filter)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
