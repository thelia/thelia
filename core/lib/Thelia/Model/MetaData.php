<?php

namespace Thelia\Model;

use Thelia\Model\Base\MetaData as BaseMetaData;

class MetaData extends BaseMetaData
{

    public function getValue()
    {
        $data = parent::getValue();

        if (parent::getIsSerialized()) {
            $data = @unserialize($data);
        }

        return $data;
    }

    public function setValue($v)
    {
        $isSerialized = false;
        $data         = $v;
        if (null !== $data) {
            if (is_array($data) || is_object($data)) {
                $data         = serialize($data);
                $isSerialized = true;
            }
        }

        parent::setIsSerialized($isSerialized);

        return parent::setValue($data);
    }

}
