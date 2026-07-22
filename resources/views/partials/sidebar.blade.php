<aside class="ik-sidebar w-64 bg-slate-950 text-white min-h-screen hidden md:flex md:flex-col">
    <div class="ik-sidebar-brand px-6 py-5 border-b border-slate-800">
        <div class="ik-brand-mark">IK</div>
        <div>
            <div class="text-lg font-bold">Internal 9</div>
            <div class="text-xs text-slate-400">WorkDesk</div>
        </div>
    </div>

    <nav class="ik-sidebar-nav flex-1 px-4 py-5 space-y-1">
        <a href="{{ route('dashboard') }}"
            class="ik-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium
           {{ request()->routeIs('dashboard') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
            <span class="w-2 h-2 rounded-full bg-current"></span>
            Dashboard
        </a>

        <a href="{{ route('admin.departments.index') }}"
            class="ik-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium
   {{ request()->routeIs('admin.departments.*') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
            <span class="w-2 h-2 rounded-full bg-current"></span>
            Departments
        </a>

        <a href="#"
            class="ik-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">
            <span class="w-2 h-2 rounded-full bg-current"></span>
            Transactions
        </a>

        <a href="#"
            class="ik-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">
            <span class="w-2 h-2 rounded-full bg-current"></span>
            Reports
        </a>

        <a href="{{ url('/panel') }}"
            class="ik-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">
            <span class="w-2 h-2 rounded-full bg-current"></span>
            Filament Panel
        </a>

        <a href="#"
            class="ik-sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-300 hover:bg-slate-900 hover:text-white">
            <span class="w-2 h-2 rounded-full bg-current"></span>
            Settings
        </a>
    </nav>

    <div class="px-4 py-5 border-t border-slate-800">
        <div class="text-xs text-slate-500">Logged in as</div>
        <div class="text-sm font-semibold truncate">
            {{ auth()->user()->name ?? 'User' }}
        </div>
    </div>
</aside>
