<nav class="nav-breadcrumb" role="navigation" aria-labelledby="breadcrumb-label">
    <strong id="breadcrumb-label">{intl l="You are here:"}</strong>
    <ul class="breadcrumb" itemprop="breadcrumb">
        <li itemscope itemtype="http://data-vocabulary.org/Breadcrumb"><a href="{url path="/login"}" itemprop="url"><span itemprop="title">{intl l="Home"}</span></a></li>

        {foreach $breadcrumbs as $breadcrumb}
        {if $breadcrumb.name}
            {if $breadcrumb@last}
                <li itemscope itemtype="http://data-vocabulary.org/Breadcrumb" class="active"><span itemprop="title">{$breadcrumb.name}</span></li>
            {else}
                <li itemscope itemtype="http://data-vocabulary.org/Breadcrumb"><a href="{if $breadcrumb.link}{$breadcrumb.link}{else}#{/if}"  title="{$breadcrumb.name}" itemprop="url"><span itemprop="title">{$breadcrumb.name}</span></a></li>
            {/if}
        {/if}
        {/foreach}
    </ul>
</nav><!-- /.nav-breadcrumb -->