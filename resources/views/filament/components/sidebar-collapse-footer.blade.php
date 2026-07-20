<div
    class="ik-sidebar-collapse-footer"
    x-data="{}"
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
        title="Expand sidebar"
    >
        <x-filament::icon
            icon="heroicon-o-chevron-right"
            class="ik-sidebar-collapse-icon"
        />
    </button>
</div>
