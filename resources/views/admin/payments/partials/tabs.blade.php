<nav aria-label="Payments sections" class="flex flex-wrap gap-2 border-b border-[#d9dee7] pb-3">
    <a href="{{ route('admin.payments.index') }}" @if(request()->routeIs('admin.payments.*')) aria-current="page" @endif
        class="rounded-xl px-4 py-2 text-sm font-semibold {{ request()->routeIs('admin.payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Payment Records</a>
    <a href="{{ route('admin.payment-options.index') }}" @if(request()->routeIs('admin.payment-options.*')) aria-current="page" @endif
        class="rounded-xl px-4 py-2 text-sm font-semibold {{ request()->routeIs('admin.payment-options.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Payment Options</a>
</nav>
