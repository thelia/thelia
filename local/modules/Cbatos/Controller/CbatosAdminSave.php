<?php

namespace Cbatos\Controller;

use Cbatos\Cbatos;
use Thelia\Controller\Admin\BaseAdminController;
use Cbatos\Model\Config;
use Cbatos\Form\ConfigureCbatos;

class CbatosAdminSave extends BaseAdminController
{
function save()
{

$error_message="";
        $conf = new Config();
        $form = new ConfigureCbatos($this->getRequest());

            $vform = $this->validateForm($form);

                $conf->setCBATOSMERCHANTID($vform->get('MerchantId')->getData())
             ->setCBATOSURLRETOUR($vform->get('Urlretour')->getData())
->setCBATOSURLAUTOMATIC($vform->get('Urlautomatic')->getData())
->setCBATOSCAPTUREDAYS($vform->get('Capturedays')->getData())
->setCBATOSDEVISES($vform->get('Devises')->getData())
->setCBATOSCUSTOMERMAIL($vform->get('Customermail')->getData())
->setCBATOSCUSTOMERID($vform->get('Customerid')->getData())
->setCBATOSCUSTOMERIP($vform->get('Customerip')->getData())
->setCBATOSPATHBIN($vform->get('PathBin')->getData())
->setCBATOSMODEDEBUG($vform->get('Modedebug')->getData())
                    ->write(Cbatos::JSON_CONFIG_PATH)
                ;

//echo 'Configuration sauvegarde avec success';
$this->redirectToRoute("admin.module.configure",array(),
            array ( 'module_code'=>"Cbatos",
                '_controller' => 'Thelia\\Controller\\Admin\\ModuleController::configureAction'));
}
}
