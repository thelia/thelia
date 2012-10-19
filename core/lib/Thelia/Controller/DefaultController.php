<?php

namespace Thelia\Controller;

use Thelia\Controller\NullControllerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * 
 * Must be the last controller call. It fixes default values
 * 
 * @author Manuel Raynaud <mraynadu@openstudio.fr>
 */

class DefaultController implements NullControllerInterface{
    /**
     * 
     * set the default value for thelia
     * 
     * In this case there is no action so we have to verify if some needed params are not missing
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function noAction(Request $request) {
        if($request->query->has('view') === false){
            $fond = "index";
            if($request->request->has('view')){
                $fond = $request->request->get('view');
            }
            $request->query->set('view', $fond);
        }
    }
}
?>
