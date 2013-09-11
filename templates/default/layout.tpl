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
<!--[if lt IE 7 ]><html class="no-js oldie ie6" lang="fr"> <![endif]-->
<!--[if IE 7 ]><html class="no-js oldie ie7" lang="fr"> <![endif]-->
<!--[if IE 8 ]><html class="no-js oldie ie8" lang="fr"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="fr" class="no-js"> <!--<![endif]-->
<head>
    <script>(function(H) { H.className=H.className.replace(/\bno-js\b/,'js') } )(document.documentElement);</script>
    <meta charset="utf-8">
    <title>{block name="page-title"}Thelia - E-commerce plateform{/block}</title>

    <meta name="description" content="">
    <meta name="generator" content="THELIA V2">
    <meta name="robots" content="index,follow">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    {block name="meta"}{/block}

    <!-- StyleSheet -->
    {stylesheets file='assets/less/styles.less' filters='less,cssembed'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}
    {debugbar_rendercss}
    {block name="stylesheet"}{/block}
</head>

<body class="page-home" itemscope itemtype="http://schema.org/WebPage">

<!-- Accessibility -->
<a class="sr-only" href="#content">Skip to content</a>

<div class="page" role="document">

<div class="header-container" itemscope itemtype="http://schema.org/WPHeader">

    <div class="navbar" itemscope itemtype="http://schema.org/SiteNavigationElement">
        <div class="container">

            <div class="navbar-header">
                <!-- .navbar-toggle is used as the toggle for collapsed navbar content -->
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".nav-main">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#">Thelia</a>
            </div>

            <!-- Place everything within .nav-collapse to hide it until above 768px -->
            <nav class="navbar-collapse collapse nav-main" role="navigation" aria-label="Main Navigation">
                <ul class="nav navbar-nav navbar-categories">
                    <li class="active"><a href="{url path="/"}" class="home" tabindex="-1">Home</a></li>
                    <li class="dropdown">
                        <a href="" data-toggle="dropdown" class="dropdown-toggle">Pages</a>
                        <ul class="dropdown-menu list-subnav" role="menu">
                            <li class="active"><a href="index.html" tabindex="-1">Index</a></li>
                            <li><a href="category.html">Category Grid</a></li>
                            <li><a href="category-list.html">Category List</a></li>
                            <li><a href="account.html">Account</a></li>
                            <li><a href="login.html">Login</a></li>
                            <li><a href="password.html">Forgot Password</a></li>
                            <li><a href="register.html">Register</a></li>
                            <li><a href="cart.html">Cart</a></li>
                            <li><a href="cart-step2.html">Cart (Step 2)</a></li>
                            <li><a href="cart-step3.html">Cart (Step 3)</a></li>
                            <li><a href="cart-step4.html">Cart (Step 4)</a></li>
                            <li><a href="product-details.html">Product details</a></li>
                            <li><a href="address.html">New address</a></li>
                        </ul>
                    </li>
                    {loop type="category" name="category.navigation" parent="0" limit="3"}
                        <li><a href="{$URL}">{$TITLE}</a></li>
                    {/loop}
                </ul>
                <ul class="nav navbar-nav navbar-cart navbar-right">
                    {loop type="auth" name="customer_info_block" roles="CUSTOMER" context="front"}
                        <li><a href="{url path="/logout"}" class="register">{intl l="Log out!"}</a></li>
                        <li><a href="{url path="/customer/account"}" class="login">{intl l="My Account"}</a></li>
                    {/loop}

                    {elseloop rel="customer_info_block" rel="customer_info_block"}
                    <li><a href="{url path="/register"}" class="register">{intl l="Register"}!</a></li>
                    <li><a href="{url path="/login"}" class="login">{intl l="Log In!"}</a></li>
                    {/elseloop}
                    <li class="dropdown">
                        <a href="cart.html" class="dropdown-toggle cart" data-toggle="dropdown">
                            Cart <span class="badge">2</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>


    <header class="container" role="banner">
        <div class="header">
            <h1 class="logo">
                <a href="{url path="/"}" class="Thelia * Since 2006 *">
                    {images file='assets/img/logo.gif'}<img src="{$asset_url}" alt="Thelia">{/images}
                </a>
            </h1>

            <div class="language-container">

                <div class="search-container">
                    <form id="form-search" action="search.html" method="get" role="search" aria-labelledby="search-label">
                        <label id="search-label" for="q">Search a product</label>
                        <div class="input-group">
                            <input type="search" name="q" id="q" placeholder="Search..." class="form-control" aria-required="true" required pattern=". { 2,}" title="Minmimum 2 characters.">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-search"><i class="icon-search"></i> <span>Search</span></button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="language-switch" aria-labelledby="language-label">
                    <span id="language-label" class="dropdown-label">Language:</span>
                    <a class="current dropdown-toggle" data-toggle="dropdown" href="language.html">{lang attr="title"}</a>
                    <ul class="select dropdown-menu">
                        {loop type="lang" name="lang_available" exclude="{lang attr="id"}"}
                            <li><a href="?lang={$CODE}">{$TITLE}</a></li>
                        {/loop}
                    </ul>
                </div>

                <div class="currency-switch" aria-labelledby="currency-label">
                    <span id="currency-label" class="dropdown-label">{intl l="Currency"}:</span>
                    <a class="current dropdown-toggle" data-toggle="dropdown" href="currency.html">{currency attr="code"}</a>
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
        {block name="breadcrumb"}{/block}
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
                            <ul>
                                <li>
                                    <a href="#">
                                        <h4 class="block-subtitle">Heading</h4>
                                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit...</p>
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <h4 class="block-subtitle">Heading</h4>
                                        <p>Lorem ipsum dolor sit amet...</p>
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <h4 class="block-subtitle">Heading</h4>
                                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit...</p>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div class="col">
                    <section class="block block-default">
                        <div class="block-heading"><h3 class="block-title">Usefull links</h3></div>
                        <div class="block-content">
                            <ul>
                                <li class="active"><a href="#" tabindex="-1">About Us </a></li>
                                <li><a href="#">Delivery & Returns</a></li>
                                <li><a href="#">Terms & Conditions </a></li>
                                <li><a href="contact.html">Contact Us</a></li>
                                <li><a href="{url path="/login"}">Login</a></li>
                                <li><a href="{url path="/register"}">Register</a></li>
                                <li><a href="checkout.html">Checkout</a></li>
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
                        <div class="block-heading"><h3 class="block-title">Newsletter</h3></div>
                        <div class="block-content">
                            <p id="newletter-describe">Sign up to receive our latest news.</p>
                            <form id="form-newsletter" action="" method="post" role="form">
                                <div class="form-group">
                                    <label for="email">Email address</label>
                                    <input type="email" name="email" id="email" class="form-control" placeholder="Your email address" aria-describedby="newletter-describe" aria-required="true" required autocomplete="off">
                                </div>
                                <button type="submit" class="btn btn-subscribe">Subscribe</button>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="col">
                    <section class="block block-contact" itemscope itemtype="http://schema.org/Organization">
                        <div class="block-heading"><h3 class="block-title">Contact Us</h3></div>
                        <div class="block-content">
                            <meta itemprop="name" content="Thelia V2">
                            <ul>
                                <li class="contact-address">
                                    <address class="adr" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
                                        <span class="street-address" itemprop="streetAddress">street name of my business</span><br>
                                        <span class="postal-code" itemprop="postalCode">75000</span>
                                        <span class="locality" itemprop="addressLocality">City, <span class="country-name">Country</span></span>
                                    </address>
                                </li>
                                <li class="contact-phone">
                                    <span class="tel" itemprop="telephone">+33 09 08 07 06 05</span>
                                </li>
                                <li class="contact-email">
                                    <a href="mailto:demo@thelia.net" class="email" itemprop="email">demo@thelia.net</a>
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
                        <li class="active"><a href="#" tabindex="-1">About Us </a></li>
                        <li><a href="#">Delivery & Returns</a></li>
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

<!-- Bootstrap -->
{javascripts file='assets/js/bootstrap/affix.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/tooltip.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/popover.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/tab.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/scrollspy.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/transition.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/alert.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/button.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/carousel.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/collapse.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/dropdown.js'}
    <script src="{$asset_url}"></script>
{/javascripts}
{javascripts file='assets/js/bootstrap/modal.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

<!-- Custom scripts -->
{javascripts file='assets/js/script.js'}
    <script src="{$asset_url}"></script>
{/javascripts}

{debugbar_renderjs}
{debugbar_renderresult}

<!--[if lt IE 9]>
<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->

{block name="after-javascript-include"}{/block}

{block name="javascript-initialization"}{/block}

</body>
</html>