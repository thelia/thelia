<nav class="nav-breadcrumb" role="navigation" aria-labelledby="breadcrumb-label">
    <strong id="breadcrumb-label" class="sr-only">{intl l="You are here:"}</strong>

    <ul class="breadcrumb" itemscope itemtype="http://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <meta itemprop="position" content="1" />
            <a itemprop="item" href="{navigate to="index"}">
                <span itemprop="name">{intl l="Home"}</span>
            </a>
        </li>
        {foreach $breadcrumbs as $breadcrumb}
            {if $breadcrumb.title}
                <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"{if $breadcrumb@last} class="active"{/if}>
                    <meta itemprop="position" content="{$breadcrumb@key+2}" />
                    <a itemprop="item" href="{$breadcrumb.url|default:'#' nofilter}" title="{$breadcrumb.title|unescape}">
                        <span itemprop="name">{$breadcrumb.title|unescape}</span>
                    </a>
                </li>
            {/if}
        {/foreach}
    </ul>
</nav><!-- /.nav-breadcrumb -->
