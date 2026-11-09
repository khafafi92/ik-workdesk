@php
    $url = $item->getUrl();
    $children = $item->getChildItems();
    $isActive = $item->isActive();
    $badge = $item->getBadge();
@endphp

<li @class(['ik-sidebar-flyout-entry', 'is-nested' => $nested])>
    @if (filled($url))
        <a
            {{ \Filament\Support\generate_href_html($url, $item->shouldOpenUrlInNewTab()) }}
            {{ $item->getExtraAttributeBag()->class(['ik-sidebar-flyout-link', 'is-active' => $isActive]) }}
            @if ($isActive) aria-current="page" @endif
            x-on:click="open = false; if (window.matchMedia('(max-width: 1023px)').matches) $store.sidebar.close()"
        >
            <span>{{ $item->getLabel() }}</span>
            @if (filled($badge))
                <x-filament::badge :color="$item->getBadgeColor($badge)" :tooltip="$item->getBadgeTooltip($badge)">{{ $badge }}</x-filament::badge>
            @endif
        </a>
    @else
        <span class="ik-sidebar-flyout-heading">{{ $item->getLabel() }}</span>
    @endif

    @if ($children)
        <ul class="ik-sidebar-flyout-children">
            @foreach ($children as $child)
                @include('filament.components.sidebar-flyout-item', ['item' => $child, 'nested' => true])
            @endforeach
        </ul>
    @endif
</li>
