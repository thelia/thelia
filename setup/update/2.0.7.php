<?php
$secret = \Thelia\Tools\TokenProvider::generateToken();

\Thelia\Model\ConfigQuery::write('form.secret', $secret, 0, 0);
