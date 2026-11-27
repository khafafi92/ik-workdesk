@php
    use Illuminate\Support\Str;

    $navigationGroups = filament()->getNavigation();
    $railItems = [];

    foreach ($navigationGroups as $groupIndex => $group) {
        $groupLabel = (string) ($group->getLabel() ?? '');
        $items = collect($group->getItems());
        $firstUrlItem = $items->first(fn ($item) => filled($item->getUrl()));

        if (! $firstUrlItem) {
            continue;
        }

        $isGroupActive = $items->contains(fn ($item) => $item->isActive());
        $displayLabel = $groupLabel !== ''
            ? \App\Services\ModuleAccessService::displayLabel($groupLabel)
            : (string) $firstUrlItem->getLabel();

        $icon = $group->getIcon() ?? $firstUrlItem->getIcon() ?? match ($groupLabel) {
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
            'Finance' => 'heroicon-o-banknotes',
            'Meeting Room' => 'heroicon-o-building-office',
            'Vehicle Booking' => 'heroicon-o-truck',
            'Notifications' => 'heroicon-o-bell',
            'Service Desk' => 'heroicon-o-lifebuoy',
            'Collaboration' => 'heroicon-o-chat-bubble-left-right',
            'Daily Reports' => 'heroicon-o-calendar-days',
            'Pengaturan Sistem' => 'heroicon-o-adjustments-horizontal',
            default => 'heroicon-o-folder',
        };

        $railItems[] = [
            'label' => $displayLabel,
            'id' => 'ik-rail-'.Str::slug($displayLabel).'-'.substr(md5($groupLabel.'|'.$firstUrlItem->getUrl().'|'.$groupIndex), 0, 8),
            'icon' => $icon,
            'active' => $isGroupActive,
            'items' => $items->all(),
            'translateLabels' => ! in_array($groupLabel, ['Permintaan', 'Pengadaan'], true),
        ];
    }

@endphp

<div class="ik-workspace-panel-header" x-show="$store.sidebar.isOpen" x-cloak>
    <div class="ik-workspace-panel-eyebrow">{{ __('Workspace') }}</div>
    <div class="ik-workspace-switcher-pill">
        <span class="ik-workspace-badge" aria-hidden="true">I9</span>
        <span class="ik-workspace-switcher-name">{{ __('Main workspace') }}</span>
    </div>
</div>

<nav class="ik-workspace-rail" aria-label="{{ __('Workspace modules') }}">
    @foreach ($railItems as $railItem)
        <div
            class="ik-workspace-rail-entry"
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
                    window.dispatchEvent(new CustomEvent('ik-sidebar-group-open', { detail: @js($railItem['id']) }))
                    this.pinned = ! byHover
                    this.open = true
                    this.$nextTick(() => { if (this.open) this.position() })
                },
                activate(byKeyboard) {
                    this.cancelClose()
                    if (this.open && this.pinned) {
                        this.open = false
                        return
                    }
                    this.show()
                    this.pinned = true
                    if (byKeyboard) this.$nextTick(() => this.$refs.panel.querySelector('a[href]')?.focus())
                },
                position() {
                    const trigger = this.$refs.trigger.getBoundingClientRect()
                    const panel = this.$refs.panel
                    const width = panel.offsetWidth
                    this.left = Math.max(12, Math.min(trigger.right + 12, window.innerWidth - width - 12))
                    this.top = Math.max(12, Math.min(trigger.top, window.innerHeight - panel.offsetHeight - 12))
                },
                closeSoon() { if (! this.pinned) this.timer = setTimeout(() => { this.open = false }, 250) },
                cancelClose() { clearTimeout(this.timer) },
            }"
            x-on:ik-sidebar-group-open.window="if ($event.detail !== @js($railItem['id'])) open = false"
            x-on:resize.window="if (open) position()"
            x-on:scroll.window.capture="if (open && ! $refs.panel.contains($event.target)) position()"
            x-on:keydown.escape.window="if (open) { $event.ikSidebarFlyoutHandled = true; suppressHover = $el.matches(':hover'); open = false; $nextTick(() => $refs.trigger.focus()) }"
            x-on:mouseenter="if (window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) show(true)"
            x-on:mouseleave="suppressHover = false; if (window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) closeSoon()"
        >
            <button
                type="button"
                x-ref="trigger"
                @class(['ik-workspace-rail-item', 'is-active' => $railItem['active']])
                aria-controls="{{ $railItem['id'] }}"
                aria-label="{{ $railItem['label'] }}"
                title="{{ $railItem['label'] }}"
                x-bind:aria-expanded="open.toString()"
                x-on:click="activate($event.detail === 0)"
            >
                <span class="ik-workspace-rail-icon-wrap">
                    <x-filament::icon :icon="$railItem['icon']" class="ik-workspace-rail-icon" />
                </span>
                <span class="ik-workspace-rail-label" x-show="$store.sidebar.isOpen">{{ $railItem['label'] }}</span>
                <x-filament::icon icon="heroicon-o-chevron-right" class="ik-workspace-rail-chevron" x-show="$store.sidebar.isOpen" />
            </button>

            <template x-teleport="body">
                <div
                    id="{{ $railItem['id'] }}"
                    x-ref="panel"
                    x-cloak
                    x-bind:style="{ left: left + 'px', top: top + 'px', display: open ? 'block' : 'none' }"
                    x-on:mouseenter="cancelClose()"
                    x-on:mouseleave="if (window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) closeSoon()"
                    x-on:click.outside="if (! $refs.trigger.contains($event.target)) open = false"
                    class="ik-sidebar-flyout ik-workspace-rail-flyout"
                    aria-label="{{ $railItem['label'] }}"
                >
                    <div class="ik-sidebar-flyout-title">{{ $railItem['label'] }}</div>
                    <ul class="ik-sidebar-flyout-list">
                        @foreach ($railItem['items'] as $item)
                            @include('filament.components.sidebar-flyout-item', [
                                'item' => $item,
                                'nested' => false,
                                'translateLabels' => $railItem['translateLabels'],
                            ])
                        @endforeach
                    </ul>
                </div>
            </template>
        </div>
    @endforeach
</nav>
