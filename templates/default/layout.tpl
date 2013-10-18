{block name="no-return-functions"}{/block}
<!doctype html>
<!--
 ______   __  __     ______     __         __     ______
/\__  _\ /\ \_\ \   /\  ___\   /\ \       /\ \   /\  __ \
\/_/\ \/ \ \  __ \  \ \  __\   \ \ \____  \ \ \  \ \  __ \
   \ \_\  \ \_\ \_\  \ \_____\  \ \_____\  \ \_\  \ \_\ \_\
    \/_/   \/_/\/_/   \/_____/   \/_____/   \/_/   \/_/\/_/


Author: Christophe Laffont
URL: http://www.thelia.net
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
    <title>{block name="page-title"}{strip}{if $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title} | {/foreach}{/if}{config key="company_name"}{/strip}{/block}</title>

    {* Meta Tags *}
    <meta name="description" content="">
    <meta name="generator" content="{intl l='Thelia V2'}">
    <meta name="robots" content="index,follow">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    {block name="meta"}{/block}

    {* Stylesheets *}
    {stylesheets file='assets/less/styles.less' filters='less,cssembed'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}

    {debugbar_rendercss}
    {block name="stylesheet"}{/block}
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
                <a class="navbar-brand" href="{navigate to="index"}">{config key="company_name"}</a>
            </div>

            <!-- Place everything within .nav-collapse to hide it until above 768px -->
            <nav class="navbar-collapse collapse nav-main" role="navigation" aria-label="{intl l="Main Navigation"}">
                {nocache}
                <ul class="nav navbar-nav navbar-cart navbar-right">
                    {loop type="auth" name="customer_info_block" roles="CUSTOMER" context="front"}
                        <li><a href="{url path="/logout"}" class="logout">{intl l="Log out!"}</a></li>
                        <li><a href="{url path="/customer/account"}" class="account">{intl l="My Account"}</a></li>
                    {/loop}
                    {elseloop rel="customer_info_block"}
                    <li><a href="{url path="/register"}" class="register">{intl l="Register!"}</a></li>
                    <li class="dropdown">
                        <a href="{url path="/login"}" class="login">{intl l="Log In!"}</a>
                        <div class="dropdown-menu">
                            {form name="thelia.customer.login"}
                            <form id="form-login-mini" action="{url path="/login"}" method="post" role="form" {form_enctype form=$form}>
                                {form_hidden_fields form=$form}
                                {form_field form=$form field="email"}
                                <div class="form-group group-email">
                                    <label for="{$label_attr.for}-mini">Email address</label>
                                    <input type="email" name="{$name}" id="{$label_attr.for}-mini" class="form-control" aria-required="true" required>
                                </div>
                                {/form_field}
                                {form_field form=$form field="password"}
                                <div class="form-group group-password">
                                    <label for="{$label_attr.for}-mini">Password</label>
                                    <input type="password" name="{$name}" id="{$label_attr.for}-mini" class="form-control" aria-required="true" required>
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
                <a href="{navigate to="index"}" title="{config key="company_name"}">
                    {images file='assets/img/logo.gif'}<img src="{$asset_url}" alt="{config key="company_name"}">{/images}
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

                <div class="language-switch" aria-labelledby="language-label">
                    <span id="language-label" class="dropdown-label">{intl l="Language:"}</span>
                    <a class="current dropdown-toggle" data-toggle="dropdown" href="{url path="/language"}">{lang attr="title"}</a>
                    <ul class="select dropdown-menu">
                        {loop type="lang" name="lang_available" exclude="{lang attr="id"}"}
                            <li><a href="?lang={$CODE}">{$TITLE}</a></li>
                        {/loop}
                    </ul>
                </div>

                <div class="currency-switch" aria-labelledby="currency-label">
                    <span id="currency-label" class="dropdown-label">{intl l="Currency:"}</span>
                    <a class="current dropdown-toggle" data-toggle="dropdown" href="{url path="/currency"}">{currency attr="code"}</a>
                    <ul class="select dropdown-menu">
                        {loop type="currency" name="currency_available" exclude="{currency attr="id"}" }
                            <li><a href="?currency={$ISOCODE}">{$SYMBOL} - {$NAME}</a></li>
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
        {block name="main-content"}{/block}
    </div><!-- /.container -->
</main><!-- /.main-container -->

<section class="footer-container" itemscope itemtype="http://schema.org/WPFooter">

    <section class="footer-banner">
        <div class="container">
            <div class="banner banner-col-3">
                <div class="col">
                    <span class="icon-truck icon-flip-horizontal"></span>
                    Free shipping <small>Orders over $50</small>
                </div>
                <div class="col">
                    <span class="icon-credit-card"></span>
                    Secure payment <small>Multi-payment plateform</small>
                </div>
                <div class="col">
                    <span class="icon-info"></span>
                    Need help ? <small>Questions ? See or F.A.Q.</small>
                </div>
            </div>
        </div>
    </section><!-- /.footer-banner -->

    <section class="footer-block">
        <div class="container">
            <div class="blocks block-col-4">
                <div class="col">
                    <section class="block block-links">
                        <div class="block-heading"><h3 class="block-title">Latest articles</h3></div>
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
                        <div class="block-heading"><h3 class="block-title">Usefull links</h3></div>
                        <div class="block-content">
                            <ul>
                                {loop name="footer_links" type="content" folder="2"}
                                    <li><a href="{$URL}">{$TITLE}</a></li>
                                {/loop}
                                <li><a href="{url path="/login"}">Login</a></li>
                                <li><a href="{url path="/register"}">Register</a></li>
                                <li><a href="{url path="/order/delivery"}">Checkout</a></li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div class="col">
                    <section class="block block-social">
                        <div class="block-heading"><h3 class="block-title">Follow us</h3></div>
                        <div class="block-content">
                            <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit.</p>
                            <ul role="presentation">
                                <li>
                                    <a href="http://facebook.com" class="facebook" data-toggle="tooltip" data-placement="top" title="facebook" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-facebook icon-light"></span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://twitter.com" class="twitter" data-toggle="tooltip" data-placement="top" title="twitter" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-twitter icon-light"></span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://instagram.com" class="instagram" data-toggle="tooltip" data-placement="top" title="instagram" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-instagram icon-light"></span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://google.com" class="google-plus" data-toggle="tooltip" data-placement="top" title="google+" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-google-plus icon-light"></span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="http://youtube.com" class="youtube" data-toggle="tooltip" data-placement="top" title="youtube" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-youtube icon-light"></span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#rss" class="rss" data-toggle="tooltip" data-placement="top" title="rss" target="_blank">
                                        <span class="icon-stack">
                                            <span class="icon-circle icon-stack-base"></span>
                                            <span class="icon-rss icon-light"></span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section class="block block-newsletter">
                        <div class="block-heading"><h3 class="block-title">{intl l="Newsletter"}</h3></div>
                        <div class="block-content">
                            <p id="newletter-describe">{intl l="Sign up to receive our latest news."}</p>
                            <form id="form-newsletter" action="{url path="/newsletter"}" method="post" role="form">
                                <div class="form-group">
                                    <label for="email">{intl l="Email address"}</label>
                                    <input type="email" name="email" id="email" class="form-control" placeholder="{intl l="Your email address"}" aria-describedby="newletter-describe" aria-required="true" required autocomplete="off">
                                </div>
                                <button type="submit" class="btn btn-subscribe">{intl l="Subscribe"}</button>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="col">
                    <section class="block block-contact" itemscope itemtype="http://schema.org/Organization">
                        <div class="block-heading"><h3 class="block-title">Contact Us</h3></div>
                        <div class="block-content">
                            <meta itemprop="name" content="{config key="company_name"}">
                            <ul>
                                <li class="contact-address">
                                    <address class="adr" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
                                        <span class="street-address" itemprop="streetAddress">street name of my business</span><br>
                                        <span class="postal-code" itemprop="postalCode">75000</span>
                                        <span class="locality" itemprop="addressLocality">City, <span class="country-name">Country</span></span>
                                    </address>
                                </li>
                                <li class="contact-phone">
                                    <span class="tel" itemprop="telephone">+33 04 44 05 31 00</span>
                                </li>
                                <li class="contact-email">
                                    <a href="mailto:demo@thelia.net" class="email" itemprop="email">info@thelia.net</a>
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
                        <li><a href="#">Site Map</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </nav>

                <section class="copyright">Copyright &copy; <time datetime="2013-08-01">2013</time> <a href="http://www.thelia.net" rel="external">Thelia</a></section>
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


{* HTML5 shim, for IE6-8 support of HTML5 elements *}
<!--[if lt IE 9]>
<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->

{debugbar_renderjs}
{debugbar_renderresult}

</body>
</html>