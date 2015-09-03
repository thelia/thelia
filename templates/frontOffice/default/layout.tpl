<!doctype html>
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

{* Declare assets directory, relative to template base directory *}
{declare_assets directory='assets'}
{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='fo.default'}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}
{block name="init"}{/block}
{block name="no-return-functions"}{/block}
{assign var="store_name" value="{config key="store_name"}"}
{assign var="store_description" value="{config key="store_description"}"}
{if not $store_name}{assign var="store_name" value="{intl l='Thelia V2'}"}{/if}
{if not $store_description}{assign var="store_description" value="$store_name"}{/if}

{* paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither *}
<!--[if lt IE 7 ]><html class="no-js oldie ie6" lang="{lang attr="code"}"> <![endif]-->
<!--[if IE 7 ]><html class="no-js oldie ie7" lang="{lang attr="code"}"> <![endif]-->
<!--[if IE 8 ]><html class="no-js oldie ie8" lang="{lang attr="code"}"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="{lang attr="code"}" class="no-js"> <!--<![endif]-->
<head>
    {hook name="main.head-top"}
    {* Test if javascript is enabled *}
    <script>(function(H) { H.className=H.className.replace(/\bno-js\b/,'js') } )(document.documentElement);</script>

    <meta charset="utf-8">

    {* Page Title *}
    <title>{block name="page-title"}{strip}{if $page_title}{$page_title}{elseif $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title|unescape} - {/foreach}{$store_name}{else}{$store_name}{/if}{/strip}{/block}</title>

    {* Meta Tags *}
    <meta name="generator" content="{intl l='Thelia V2'}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    {block name="meta"}
        <meta name="description" content="{if $page_description}{$page_description}{else}{$store_description|strip|truncate:120}{/if}">
    {/block}

    {* Stylesheets *}
    {stylesheets file='assets/css/styles.css'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}

    {hook name="main.stylesheet"}

    {block name="stylesheet"}{/block}

    {* Favicon *}
    {images file='assets/img/favicon.ico'}<link rel="shortcut icon" type="image/x-icon" href="{$asset_url}">{/images}
    {images file='assets/img/favicon.png'}<link rel="icon" type="image/png" href="{$asset_url}" />{/images}

    {* Feeds *}
    <link rel="alternate" type="application/rss+xml" title="{intl l='All products'}" href="{url path="/feed/catalog/{lang attr="locale"}"}" />
    <link rel="alternate" type="application/rss+xml" title="{intl l='All contents'}" href="{url path="/feed/content/{lang attr="locale"}"}" />
    <link rel="alternate" type="application/rss+xml" title="{intl l='All brands'}"   href="{url path="/feed/brand/{lang attr='locale'}"}" />
    {block name="feeds"}{/block}

    {* HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries *}
    <!--[if lt IE 9]>
    <script src="//oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="//oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
    {hook name="main.head-bottom"}
</head>
<body class="{block name="body-class"}{/block}" itemscope itemtype="http://schema.org/WebPage">
{hook name="main.body-top"}
<!-- Accessibility -->
<a class="sr-only" href="#content">{intl l="Skip to content"}</a>

<div class="page" role="document">

<div class="header-container" itemscope itemtype="http://schema.org/WPHeader">
    {hook name="main.header-top"}
    <div class="navbar navbar-secondary" itemscope itemtype="http://schema.org/SiteNavigationElement">
        <div class="container">

            <div class="navbar-header">
                <!-- .navbar-toggle is used as the toggle for collapsed navbar content -->
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".nav-secondary">
                    <span class="sr-only">{intl l="Toggle navigation"}</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{navigate to="index"}">{$store_name}</a>
            </div>

            {ifhook rel="main.navbar-secondary"}
            {* Place everything within .nav-collapse to hide it until above 768px *}
            <nav class="navbar-collapse collapse nav-secondary" role="navigation" aria-label="{intl l="Secondary Navigation"}">
                {hook name="main.navbar-secondary"}
            </nav>
            {/ifhook}
        </div>
    </div>


    <header class="container" role="banner">
        <div class="header">
            <h1 class="logo">
                <a href="{navigate to="index"}" title="{$store_name}">
                    {images file='assets/img/logo.gif'}<img src="{$asset_url}" alt="{$store_name}">{/images}
                </a>
            </h1>
            {hook name="main.navbar-primary"}
        </div>
    </header><!-- /.header -->

    {hook name="main.header-bottom"}
</div><!-- /.header-container -->

<main class="main-container" role="main">
    <div class="container">
        {hook name="main.content-top"}
        {block name="breadcrumb"}{include file="misc/breadcrumb.tpl"}{/block}
        <div id="content">{block name="main-content"}{/block}</div>
        {hook name="main.content-bottom"}
    </div><!-- /.container -->
</main><!-- /.main-container -->

<section class="footer-container" itemscope itemtype="http://schema.org/WPFooter">

    {ifhook rel="main.footer-top"}
    <section class="footer-block">
        <div class="container">
            <div class="blocks block-col-3">
                {hook name="main.footer-top"}
            </div>
        </div>
    </section>
    {/ifhook}
    {elsehook rel="main.footer-top"}
    <section class="footer-banner">
        <div class="container">
            <div class="banner banner-col-3">
                <div class="col">
                    <span class="icon-truck icon-flip-horizontal"></span>
                    {intl l="Free shipping"} <small>{intl l="Orders over $50"}</small>
                </div>
                <div class="col">
                    <span class="icon-credit-card"></span>
                    {intl l="Secure payment"} <small>{intl l="Multi-payment platform"}</small>
                </div>
                <div class="col">
                    <span class="icon-info"></span>
                    {intl l="Need help ?"} <small>{intl l="Questions ? See our F.A.Q."}</small>
                </div>
            </div>
        </div>
    </section><!-- /.footer-banner -->
    {/elsehook}

    {ifhook rel="main.footer-body"}
    <section class="footer-block">
        <div class="container">
            <div class="blocks block-col-4">
                {hookblock name="main.footer-body"  fields="id,class,title,content"}
                    {forhook rel="main.footer-body"}
                    <div class="col">
                        <section {if $id} id="{$id}"{/if} class="block {if $class} block-{$class}{/if}">
                            <div class="block-heading"><h3 class="block-title">{$title}</h3></div>
                            <div class="block-content">
                                {$content nofilter}
                            </div>
                        </section>
                    </div>
                    {/forhook}
                {/hookblock}
            </div>
        </div>
    </section>
    {/ifhook}

    {ifhook rel="main.footer-bottom"}
    <footer class="footer-info" role="contentinfo">
        <div class="container">
            <div class="info">
                {hook name="main.footer-bottom"}
                <section class="copyright">{intl l="Copyright"} &copy; <time datetime="{'Y-m-d'|date}">{'Y'|date}</time> <a href="http://thelia.net" rel="external">Thelia</a></section>
            </div>
        </div>
    </footer>
    {/ifhook}
    {elsehook rel="main.footer-bottom"}
    <footer class="footer-info" role="contentinfo">
        <div class="container">
            <div class="info">
                <nav class="nav-footer" role="navigation">
                    <ul>
                        {$folder_information={config key="information_folder_id"}}
                        {if $folder_information}
                            {loop name="footer_links" type="content" folder=$folder_information}
                                <li><a href="{$URL nofilter}">{$TITLE}</a></li>
                            {/loop}
                        {/if}
                        <li><a href="{url path="/contact"}">{intl l="Contact Us"}</a></li>
                    </ul>
                </nav>
                <section class="copyright">{intl l="Copyright"} &copy; <time datetime="{'Y-m-d'|date}">{'Y'|date}</time> <a href="http://thelia.net" rel="external">Thelia</a></section>
            </div>
        </div>
    </footer><!-- /.footer-info -->
    {/elsehook}

</section><!-- /.footer-container -->

</div><!-- /.page -->

{block name="before-javascript-include"}{/block}
<!-- JavaScript -->
<!--[if lt IE 9]><script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script> <!--<![endif]-->
<script>
    if (typeof jQuery == 'undefined') {
        {javascripts file='assets/js/libs/jquery.js'}
            document.write(unescape("%3Cscript src='{$asset_url}' %3E%3C/script%3E"));
        {/javascripts}
    }
</script>

<script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/jquery.validate.min.js"></script>
{if {lang attr="code"} != 'en'}
    <script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/localization/messages_{lang attr="code"}.js"></script>
{/if}


{javascripts file='assets/js/bootstrap/bootstrap.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

{javascripts file='assets/js/plugins/bootbox/bootbox.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

{hook name="main.after-javascript-include"}

{block name="after-javascript-include"}{/block}

{hook name="main.javascript-initialization"}

{block name="javascript-initialization"}{/block}

<!-- Custom scripts -->
{javascripts file='assets/js/script.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{hook name="main.body-bottom"}
</body>
</html>
