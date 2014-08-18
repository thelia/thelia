<!--
 ______   __  __     ______     __         __     ______
/\__  _\ /\ \_\ \   /\  ___\   /\ \       /\ \   /\  __ \
\/_/\ \/ \ \  __ \  \ \  __\   \ \ \____  \ \ \  \ \  __ \
   \ \_\  \ \_\ \_\  \ \_____\  \ \_____\  \ \_\  \ \_\ \_\
    \/_/   \/_/\/_/   \/_____/   \/_____/   \/_/   \/_/\/_/


Copyright (c) OpenStudio
email : info@thelia.net
web : http://www.thelia.net

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the
GNU General Public License : http://www.gnu.org/licenses/
-->

<!--
TEMPLATE-TEST-HOOK
-->

{* Declare assets directory, relative to template base directory *}
{declare_assets directory='assets'}

{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='fo.default'}

{hook name="main.head-top"}

{hook name="main.stylesheet"}

{hook name="main.body-top"}

{hook name="main.header-top"}

{hook name="main.navbar-secondary"}
{ifhook rel="main.navbar-secondary"}
    ::main.navbar-secondary ifhook::
{/ifhook}
{elsehook rel="main.navbar-secondary"}
    ::main.navbar-secondary elsehook::
{/elsehook}

{hook name="main.navbar-primary"}
{ifhook rel="main.navbar-primary"}
    ::main.navbar-primary ifhook::
{/ifhook}
{elsehook rel="main.navbar-primary"}
    ::main.navbar-primary elsehook::
{/elsehook}

{hook name="main.header-bottom"}

{hook name="main.content-top"}


{block name="main-content"}{/block}


{hook name="main.content-bottom"}

{ifhook rel="main.footer-top"}
    {hook name="main.footer-top"}
{/ifhook}
{elsehook rel="main.footer-top"}
    ::NO main.footer-top::
{/elsehook}

{ifhook rel="product.additional"}
{hookblock name="product.additional"}
{forhook rel="product.additional"}
    ::product.additional ifhook::
{/forhook}
{/hookblock}
{/ifhook}
{elsehook rel="product.additional"}
    ::product.additional elsehook::
{/elsehook}



{ifhook rel="main.footer-body"}
    ::main.footer-body ifhook::
    {hookblock name="main.footer-body"}
        {forhook rel="main.footer-body"}
            ::main.footer-body {$id} {$class} {$content}::
        {/forhook}
    {/hookblock}
{/ifhook}
{elsehook rel="main.footer-body"}
    ::main.footer-body elsehook::
{/elsehook}

{ifhook rel="main.footer-bottom"}
    {hook name="main.footer-bottom"}
{/ifhook}
{elsehook rel="main.footer-bottom"}
    ::NO main.footer-bottom::
{/elsehook}

{hook name="main.after-javascript-include"}

{block name="after-javascript-include"}{/block}

{hook name="main.javascript-initialization"}

{block name="javascript-initialization"}{/block}

{hook name="main.body-bottom"}
