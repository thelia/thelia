<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 20/06/13
 * Time: 12:05
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Admin\Controller;


class AdminController extends BaseAdminController {

    public function indexAction()
    {
        return $this->render("login.html");
    }
}