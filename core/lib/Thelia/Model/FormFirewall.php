<?php

namespace Thelia\Model;

use Thelia\Model\Base\FormFirewall as BaseFormFirewall;

class FormFirewall extends BaseFormFirewall
{
    public function resetAttempts()
    {
        $this->setAttempts(0)->save();

        return $this;
    }

    public function incrementAttempts()
    {
        $this->setAttempts(
            $this->getAttempts() + 1
        );

        $this->save();

        return $this;
    }
}
