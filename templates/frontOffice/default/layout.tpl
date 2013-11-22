{* Declare assets directory, relative to template base directory *}
{declare_assets directory='assets'}
{block name="no-return-functions"}{/block}
{assign var="store_name" value="{config key="store_name"}"}
{if not $store_name}
    {assign var="store_name" value="{intl l='Thelia V2'}"}
{/if}
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

{* paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither *}
<!--[if lt IE 7 ]><html class="no-js oldie ie6" lang="{lang attr="code"}"> <![endif]-->
<!--[if IE 7 ]><html class="no-js oldie ie7" lang="{lang attr="code"}"> <![endif]-->
<!--[if IE 8 ]><html class="no-js oldie ie8" lang="{lang attr="code"}"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="{lang attr="code"}" class="no-js"> <!--<![endif]-->
<head>

    {* Test if javascript is enabled *}
    <script>(function(H) { H.className=H.className.replace(/\bno-js\b/,'js') } )(document.documentElement);</script>

    <meta charset="utf-8">

    {* Page Title *}
    <title>{block name="page-title"}{strip}{if $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title} - {/foreach}{/if}{$store_name}{/strip}{/block}</title>

    {* Meta Tags *}
    <meta name="generator" content="{intl l='Thelia V2'}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    {block name="meta"}
        <meta name="description" content="{$store_name}">
        <meta name="robots" content="noindex,nofollow">
    {/block}

    {* Stylesheets *}
    {stylesheets file='assets/less/styles.less' filters='less'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}

    {block name="stylesheet"}{/block}

    {* Favicon *}
    {images file='assets/img/favicon.ico'}<link rel="shortcut icon" type="image/x-icon" href="{$asset_url}">{/images}
    {images file='assets/img/favicon.png'}<link rel="icon" type="image/png" href="{$asset_url}" />{/images}

    {* HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries *}
    <!--[if lt IE 9]>
    <script src="//oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="//oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
</head>

<body class="{block name="body-class"}{/block}" itemscope itemtype="http://schema.org/WebPage">

<!-- Accessibility -->
<a class="sr-only" href="#content">{intl l="Skip to content"}</a>

<div class="page" role="document">

<div class="header-container" itemscope itemtype="http://schema.org/WPHeader">

    <div class="navbar" itemscope itemtype="http://schema.org/SiteNavigationElement">
        <div class="container">

            <div class="navbar-header">
                <!-- .navbar-toggle is used as the toggle for collapsed navbar content -->
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".nav-main">
                    <span class="sr-only">{intl l="Toggle navigation"}</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{navigate to="index"}">{$store_name}</a>
            </div>

            <!-- Place everything within .nav-collapse to hide it until above 768px -->
            <nav class="navbar-collapse collapse nav-main" role="navigation" aria-label="{intl l="Main Navigation"}">
                {nocache}
                <ul class="nav navbar-nav navbar-cart navbar-right">
                    {loop type="auth" name="customer_info_block" role="CUSTOMER" context="front"}
                        <li><a href="{url path="/logout"}" class="logout">{intl l="Log out!"}</a></li>
                        <li><a href="{url path="/account"}" class="account">{intl l="My Account"}</a></li>
                    {/loop}
                    {elseloop rel="customer_info_block"}
                    <li><a href="{url path="/register"}" class="register">{intl l="Register!"}</a></li>
                    <li class="dropdown">
                        <a href="{url path="/login"}" class="login">{intl l="Log In!"}</a>
                        <div class="dropdown-menu">
                            {form name="thelia.front.customer.login"}
                            <form id="form-login-mini" action="{url path="/login"}" method="post" {form_enctype form=$form}>
                                {form_hidden_fields form=$form}
                                {form_field form=$form field="email"}
                                <div class="form-group group-email">
                                    <label for="{$label_attr.for}-mini">{intl l="Email address"}</label>
                                    <input type="email" name="{$name}" id="{$label_attr.for}-mini" class="form-control" maxlength="255" aria-required="true" required>
                                </div>
                                {/form_field}
                                {form_field form=$form field="password"}
                                <div class="form-group group-password">
                                    <label for="{$label_attr.for}-mini">{intl l="Password"}</label>
                                    <input type="password" name="{$name}" id="{$label_attr.for}-mini" class="form-control" maxlength="255" aria-required="true" required>
                                </div>
                                {/form_field}
                                {form_field form=$form field="account"}
                                <input type="hidden" name="{$name}" value="1">
                                {/form_field}
                                <div class="group-btn">
                                    <button type="submit" class="btn btn-login-mini">{intl l="Sign In"}</button>
                                    <a href="{url path="/register"}" class="btn btn-register-mini">{intl l="Register"}</a>
                                </div>
                            </form>
                            {/form}
                        </div>
                    </li>
                    {/elseloop}
                    {include file="includes/mini-cart.html" nocache}
                </ul>
                {/nocache}
                <ul class="nav navbar-nav navbar-categories">
                    <li><a href="{navigate to="index"}" class="home">{intl l="Home"}</a></li>
                    {loop type="category" name="category.navigation" parent="0"}
                        <li><a href="{$URL}">{$TITLE}</a></li>
                    {/loop}
                </ul>
            </nav>
        </div>
    </div>


    <header class="container" role="banner">
        <div class="header">
            <h1 class="logo">
                <a href="{navigate to="index"}" title="{$store_name}">
                    {images file='assets/img/logo.gif'}<img src="{$asset_url}" alt="{$store_name}">{/images}
                </a>
            </h1>

            <div class="language-container">

                <div class="search-container">
                    <form id="form-search" action="{url path="/search"}" method="get" role="search" aria-labelledby="search-label">
                        <label id="search-label" for="q">{intl l="Search a product"}</label>
                        <div class="input-group">
                            <input type="search" name="q" id="q" placeholder="{intl l="Search..."}" class="form-control" autocomplete="off" aria-required="true" required pattern=".{ldelim}2,{rdelim}" title="{intl l="Minimum 2 characters."}">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-search"><i class="icon-search"></i> <span>{intl l="Search"}</span></button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="language-switch" aria-labelledby="language-label" role="form">
                    <span id="language-label" class="dropdown-label">{intl l="Language:"}</span>
                    <a class="current dropdown-toggle" data-toggle="dropdown" href="{url path="/language"}">{lang attr="title"}</a>
                    <ul class="select dropdown-menu">
                        {loop type="lang" name="lang_available" exclude="{lang attr="id"}"}
                            <li><a href="{url path="{navigate to="current"}" lang={$CODE}}">{$TITLE}</a></li>
                        {/loop}
                    </ul>
                </div>

                <div class="currency-switch" aria-labelledby="currency-label" role="form">
                    <span id="currency-label" class="dropdown-label">{intl l="Currency:"}</span>
                    <a class="current dropdown-toggle" data-toggle="dropdown" href="{url path="/currency"}">{currency attr="code"}</a>
                    <ul class="select dropdown-menu">
                        {loop type="currency" name="currency_available" exclude="{currency attr="id"}" }
                            <li><a href="{url path="{navigate to="current"}" currency={$ISOCODE}}">{$SYMBOL} - {$NAME}</a></li>
                        {/loop}
                    </ul>
                </div>
            </div>
        </div>

    </header><!-- /.header -->

</div><!-- /.header-container -->

<main class="main-container" role="main">
    <div class="container">
        {block name="breadcrumb"}{include file="misc/breadcrumb.tpl"}{/block}
        <div id="content">{block name="main-content"}{/block}</div>
    </div><!-- /.container -->
</main><!-- /.main-container -->

<section class="footer-container" itemscope itemtype="http://schema.org/WPFooter">

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
                    {intl l="Need help ?"} <small>{intl l="Questions ? See or F.A.Q."}</small>
                </div>
            </div>
        </div>
    </section><!-- /.footer-banner -->

    <section class="footer-block">
        <div class="container">
            <div class="blocks block-col-4">
                <div class="col">
                    <section class="block block-links">
                        <div class="block-heading"><h3 class="block-title">{intl l="Latest articles"}</h3></div>
                        <div class="block-content">
                            {ifloop rel="blog.articles"}
                                <ul>
                                    {loop type="content" name="blog.articles" folder="1" limit="3"}
                                        <li>
                                            <a href="{$URL}">
                                                <h4 class="block-subtitle">{$TITLE}</h4>
                                                <p>{$CHAPO}</p>
                                            </a>
                                        </li>
                                    {/loop}
                                </ul>
                            {/ifloop}
                            {elseloop rel="blog.articles"}
                                <ul>
                                    <li>{intl l="No articles currently"}</li>
                                </ul>
                            {/elseloop}
                        </div>
                    </section>
                </div>
                <div class="col">
                    <section class="block block-default">
                        <div class="block-heading"><h3 class="block-title">{intl l="Useful links"}</h3></div>
                        <div class="block-content">
                            <ul>
                                {loop name="footer_links" type="content" folder="2"}
                                    <li><a href="{$URL}">{$TITLE}</a></li>
                                {/loop}
                                {loop type="auth" name="customer_is_logged" role="CUSTOMER" context="front"}
                                    <li><a href="{url path="/logout"}" class="logout">{intl l="Log out!"}</a></li>
                                    <li><a href="{url path="/account"}" class="account">{intl l="My Account"}</a></li>
                                {/loop}
                                {elseloop rel="customer_is_logged"}
                                <li><a href="{url path="/login"}">{intl l="Login"}</a></li>
                                <li><a href="{url path="/register"}">{intl l="Register"}</a></li>
                                {/elseloop}
                                <li><a href="{url path="/cart"}">{intl l="Cart"}</a></li>
                                <li><a href="{url path="/order/delivery"}">{intl l="Checkout"}</a></li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div class="col">
                    <section class="block block-social">
                        <div class="block-heading"><h3 class="block-title">{intl l="Follow us"}</h3></div>
                        <div class="block-content">
                            <p>{intl l="Follow us introduction"}</p>
                            <ul role="presentation">
                                <li>
                                    <a href="http://facebook.com" rel="nofollow" class="facebook" data-toggle="tooltip" data-placement="top" title="{intl l="Facebook"}" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-facebook icon-light"></span>
                                        </span>
                                        <span class="visible-print">{intl l="Facebook"}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://twitter.com" rel="nofollow" class="twitter" data-toggle="tooltip" data-placement="top" title="{intl l="Twitter"}" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-twitter icon-light"></span>
                                        </span>
                                        <span class="visible-print">{intl l="Twitter"}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://instagram.com" rel="nofollow" class="instagram" data-toggle="tooltip" data-placement="top" title="{intl l="Instagram"}" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-instagram icon-light"></span>
                                        </span>
                                        <span class="visible-print">{intl l="Instagram"}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://www.google.com" rel="nofollow" class="google-plus" data-toggle="tooltip" data-placement="top" title="{intl l="Google+"}" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-google-plus icon-light"></span>
                                        </span>
                                        <span class="visible-print">{intl l="Google+"}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://www.youtube.com" rel="nofollow" class="youtube" data-toggle="tooltip" data-placement="top" title="{intl l="Youtube"}" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-youtube icon-light"></span>
                                        </span>
                                        <span class="visible-print">{intl l="Youtube"}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#rss" class="rss" rel="nofollow" data-toggle="tooltip" data-placement="top" title="{intl l="RSS"}" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-rss icon-light"></span>
                                        </span>
                                        <span class="visible-print">{intl l="RSS"}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section class="block block-newsletter">
                        <div class="block-heading"><h3 class="block-title">{intl l="Newsletter"}</h3></div>
                        <div class="block-content">
                            <p id="newsletter-describe">{intl l="Sign up to receive our latest news."}</p>
                            {form name="thelia.front.newsletter"}
                            <form id="form-newsletter-mini" action="{url path="/newsletter"}" method="post">
                                {form_hidden_fields form=$form}
                                {form_field form=$form field="email"}
                                <div class="form-group">
                                    <label for="{$label_attr.for}-mini">{intl l="Email address"}</label>
                                    <input type="email" name="{$name}" id="{$label_attr.for}-mini" class="form-control" maxlength="255" placeholder="{intl l="Your email address"}" aria-describedby="newsletter-describe" {if $required} aria-required="true" required{/if} autocomplete="off">
                                </div>
                                {/form_field}
                                <button type="submit" class="btn btn-subscribe">{intl l="Subscribe"}</button>
                            </form>
                            {/form}
                        </div>
                    </section>
                </div>

                <div class="col">
                    <section class="block block-contact" itemscope itemtype="http://schema.org/Organization">
                        <div class="block-heading"><h3 class="block-title">{intl l="Contact Us"}</h3></div>
                        <div class="block-content">
                            <meta itemprop="name" content="{$store_name}">
                            <ul>
                                <li class="contact-address">
                                    <address class="adr" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
                                        <span class="street-address" itemprop="streetAddress">Street name of my business</span><br>
                                        <span class="postal-code" itemprop="postalCode">75000</span>
                                        <span class="locality" itemprop="addressLocality">City, <span class="country-name">France</span></span>
                                    </address>
                                </li>
                                <li class="contact-phone">
                                    <span class="tel" itemprop="telephone">+33 (0)0 00 00 00 00</span>
                                </li>
                                <li class="contact-email">
                                    {mailto address="contact@yourdomain.com" encode="hex" extra='class="email" itemprop="email"'}
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section><!-- /.footer-block -->


    <footer class="footer-info" role="contentinfo">
        <div class="container">
            <div class="info">
                <nav class="nav-footer" role="navigation">
                    <ul>
                        {loop name="footer_links" type="content" folder="2"}
                            <li><a href="{$URL}">{$TITLE}</a></li>
                        {/loop}
                        {*<li><a href="#">Site Map</a></li>
                        <li><a href="#">Terms & Conditions</a></li>*}
                        <li><a href="{url path="/contact"}">{intl l="Contact Us"}</a></li>
                    </ul>
                </nav>

                <section class="copyright">{intl l="Copyright"} &copy; <time datetime="{'Y-m-d'|date}">{'Y'|date}</time> <a href="http://thelia.net" rel="external">Thelia</a></section>
            </div>
        </div>
    </footer><!-- /.footer-info -->

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

<script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js"></script>


{javascripts file='assets/js/bootstrap/bootstrap.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

{javascripts file='assets/js/plugins/bootbox/bootbox.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

{block name="after-javascript-include"}{/block}

{block name="javascript-initialization"}{/block}

<!-- Custom scripts -->
{javascripts file='assets/js/script.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
</body>
</html>