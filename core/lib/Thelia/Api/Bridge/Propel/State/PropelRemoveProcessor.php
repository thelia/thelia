<?php

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;

class PropelRemoveProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $propelModel = $data->getPropelModel();

        $propelModel->delete();
    }
}
