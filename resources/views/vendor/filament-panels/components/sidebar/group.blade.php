@props([
    'active' => false,
    'collapsible' => true,
    'icon' => null,
    'items' => [],
    'label' => null,
    'sidebarCollapsible' => true,
    'subNavigation' => false,
])

@if ($subNavigation || blank($label))
    @include('filament.components.original-sidebar-group')
@else
    @php
        $groupId = 'ik-nav-' . \Illuminate\Support\Str::slug($label) . '-' . substr(md5($label), 0, 8);
        $groupIcon = $icon ?? match ($label) {
            'Master Data' => 'heroicon-o-squares-2x2',
            'Attendance Report' => 'heroicon-o-clock',
            'Surat' => 'heroicon-o-envelope',
            'Reports' => 'heroicon-o-chart-bar',
            'LTRO Management' => 'heroicon-o-cog-6-tooth',
            'ATK' => 'heroicon-o-pencil-square',
            'Infrastruktur IT' => 'heroicon-o-computer-desktop',
            'Inventori' => 'heroicon-o-archive-box',
            'Permintaan' => 'heroicon-o-clipboard-document-list',
            'Pengadaan' => 'heroicon-o-shopping-cart',
            'Meeting Room' => 'heroicon-o-building-office',
            'Vehicle Booking' => 'heroicon-o-truck',
            'Notifications' => 'heroicon-o-bell',
            'Service Desk' => 'heroicon-o-lifebuoy',
            'Collaboration' => 'heroicon-o-chat-bubble-left-right',
            'Daily Reports' => 'heroicon-o-calendar-days',
            default => 'heroicon-o-folder',
        };
    @endphp

    <li
        {{ $attributes->class(['fi-sidebar-group', 'ik-sidebar-group', 'fi-active' => $active]) }}
        data-group-label="{{ $label }}"
        x-data="{
            open: false,
            pinned: false,
            suppressHover: false,
            timer: null,
            left: 0,
            top: 0,
            show(byHover = false) {
                clearTimeout(this.timer)
                if (byHover && this.suppressHover) return
                if (this.open) return
                window.dispatchEvent(new CustomEvent('ik-sidebar-group-open', { detail: @js($groupId) }))
                this.pinned = ! byHover
                this.open = true
                this.$nextTick(() => { if (this.open) this.position() })
            },
            position() {
                const anchor = this.$refs.trigger.getBoundingClientRect()
                const panel = this.$refs.panel
                const width = panel.offsetWidth
                this.left = Math.max(12, Math.min(anchor.right + 12, window.innerWidth - width - 12))
                this.top = Math.max(12, Math.min(anchor.top, window.innerHeight - panel.offsetHeight - 12))
            },
            closeSoon() { if (! this.pinned) this.timer = setTimeout(() => { this.open = false }, 250) },
            cancelClose() { clearTimeout(this.timer) },
        }"
        x-on:ik-sidebar-group-open.window="if ($event.detail !== @js($groupId)) open = false"
        x-on:resize.window="if (open) position()"
        x-on:scroll.window.capture="if (open && ! $refs.panel.contains($event.target)) position()"
        x-on:keydown.escape.window="if (open) { $event.ikSidebarFlyoutHandled = true; suppressHover = true; open = false; $nextTick(() => $refs.trigger.focus()) }"
        x-on:mouseenter="if (window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) show(true)"
        x-on:mouseleave="suppressHover = false; if (window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) closeSoon()"
    >
        <button
            type="button"
            x-ref="trigger"
            class="ik-sidebar-group-trigger"
            aria-controls="{{ $groupId }}"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-label="$store.sidebar.isOpen ? null : @js($label)"
            x-bind:title="$store.sidebar.isOpen ? null : @js($label)"
            x-on:click="cancelClose(); open ? (pinned ? open = false : pinned = true) : show()"
        >
            <x-filament::icon :icon="$groupIcon" class="ik-sidebar-group-icon" />
            <span class="fi-sidebar-group-label" x-show="$store.sidebar.isOpen">{{ $label }}</span>
            <x-filament::icon icon="heroicon-o-chevron-right" class="ik-sidebar-group-chevron" x-show="$store.sidebar.isOpen" />
        </button>

        <ul class="fi-sidebar-group-items" hidden></ul>

        <template x-teleport="body">
            <div
                id="{{ $groupId }}"
                x-ref="panel"
                x-cloak
                x-bind:style="{ left: left + 'px', top: top + 'px', display: open ? 'block' : 'none' }"
                x-on:mouseenter="cancelClose()"
                x-on:mouseleave="if (window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) closeSoon()"
                x-on:click.outside="if (! $refs.trigger.contains($event.target)) open = false"
                class="ik-sidebar-flyout"
                aria-label="{{ $label }}"
            >
                <div class="ik-sidebar-flyout-title">{{ $label }}</div>
                <ul class="ik-sidebar-flyout-list">
                    @foreach ($items as $item)
                        @include('filament.components.sidebar-flyout-item', ['item' => $item, 'nested' => false])
                    @endforeach
                </ul>
            </div>
        </template>
    </li>
@endif
