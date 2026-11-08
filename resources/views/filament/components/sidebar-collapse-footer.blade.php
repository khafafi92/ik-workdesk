<div
    class="ik-sidebar-collapse-footer"
    x-data="{}"
    x-on:keydown.escape.window="
        if (window.matchMedia('(max-width: 1023px)').matches && $store.sidebar.isOpen) {
            $store.sidebar.close()
            $nextTick(() => document.querySelector('.fi-topbar-open-sidebar-btn')?.focus())
        }
    "
    x-init="
        $nextTick(() => {
            document
                .querySelectorAll('.fi-topbar-collapse-sidebar-btn-ctn')
                .forEach((element) => element.remove())
        })
    "
>
    <button
        type="button"
        class="ik-sidebar-collapse-button"
        x-cloak
        x-show="$store.sidebar.isOpen"
        x-on:click="$store.sidebar.close()"
        aria-label="Collapse sidebar"
        aria-controls="fi-main-sidebar"
        x-bind:aria-expanded="$store.sidebar.isOpen"
        title="Collapse sidebar"
    >
        <x-filament::icon
            icon="heroicon-o-chevron-left"
            class="ik-sidebar-collapse-icon"
        />

        <span class="ik-sidebar-collapse-label">Collapse</span>
    </button>

    <button
        type="button"
        class="ik-sidebar-collapse-button"
        x-cloak
        x-show="! $store.sidebar.isOpen"
        x-on:click="$store.sidebar.open()"
        aria-label="Expand sidebar"
        aria-controls="fi-main-sidebar"
        x-bind:aria-expanded="$store.sidebar.isOpen"
        title="Expand sidebar"
    >
        <x-filament::icon
            icon="heroicon-o-chevron-right"
            class="ik-sidebar-collapse-icon"
        />
    </button>
</div>
