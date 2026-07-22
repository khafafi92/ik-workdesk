<header class="ik-topbar h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
    <div>
        <p class="text-sm text-slate-500">Welcome back,</p>
        <p class="text-sm font-semibold text-slate-900">
            {{ auth()->user()->name ?? 'User' }}
        </p>
    </div>

    <div class="flex items-center gap-3">
        <a href="{{ route('profile.edit') }}"
            class="ik-secondary-button px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold hover:bg-slate-50">
            Profile
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit"
                class="ik-primary-button px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-700">
                Logout
            </button>
        </form>
    </div>
</header>
