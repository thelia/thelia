<?php

namespace Thelia\Core\Template;

/**
 *
 *
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 *
 */

interface ParserInterface
{
    /**
     *
     */
    public function getContent();

    public function setContent($content);

    public function getStatus();

    public function setStatus($status);
}
