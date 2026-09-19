<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </span>
                    <h2 class="text-xl font-bold text-slate-800 leading-tight">
                        {{ __('API Key & Access Management') }}
                    </h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Kelola otorisasi client eksternal, kontrol hak akses (scopes), dan pantau log traffic FreeRADIUS API secara real-time.
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="/docs/api" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Dokumentasi API
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Toast / Flash Notification -->
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl shadow-sm flex items-start gap-3 text-emerald-800 transition">
                    <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xs">
                        <span class="font-bold text-sm block">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <!-- Alert Notifikasi API Key Baru Dibuat / Diregenerate -->
            @if(session('new_key'))
                <div class="p-5 bg-gradient-to-r from-amber-50 to-amber-100/60 border border-amber-300 rounded-2xl shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="p-2 bg-amber-200/80 text-amber-800 rounded-lg shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <div>
                                <h4 class="font-bold text-sm text-amber-900">Salin & Simpan API Secret Key Anda Sekarang!</h4>
                                <p class="text-xs text-amber-800 mt-0.5">
                                    Secret Key ini <strong>hanya ditampilkan satu kali</strong> saat dibuat atau di-rotate. Jangan bagikan kepada siapapun.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                        <div class="relative flex-1">
                            <input id="newApiKeyInput" type="text" readonly value="{{ session('new_key') }}" 
                                class="w-full font-mono text-xs sm:text-sm font-semibold bg-white text-slate-800 px-3.5 py-2.5 rounded-xl border border-amber-300 shadow-inner select-all focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>
                        <button onclick="copyToClipboard('newApiKeyInput', this)" type="button" 
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white text-xs font-semibold rounded-xl shadow transition shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            <span>Salin API Key</span>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Top Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <!-- Total Keys -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total API Keys</span>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-1">{{ $totalKeys }}</div>
                        <span class="text-[11px] text-slate-400">Terdaftar di database</span>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                </div>

                <!-- Active Keys -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kunci Aktif</span>
                        <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mt-1 flex items-center gap-2">
                            <span>{{ $activeKeys }}</span>
                            @if($totalKeys > 0)
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ round(($activeKeys / $totalKeys) * 100) }}%
                                </span>
                            @endif
                        </div>
                        <span class="text-[11px] text-slate-400">Memiliki hak akses aktif</span>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                </div>

                <!-- Traffic Logged -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Audit Logs</span>
                        <div class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-1">{{ number_format($totalRequests) }}</div>
                        <span class="text-[11px] text-slate-400">Total hit request terekam</span>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Main Layout: Form (Left) & Tables (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Form Buat API Key Baru (4 Cols) -->
                <div class="lg:col-span-4">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-5 sticky top-6">
                        <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                            <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-md">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Generate API Key Baru</h3>
                                <p class="text-[11px] text-slate-500">Tambahkan klien baru untuk integrasi</p>
                            </div>
                        </div>

                        <form action="{{ route('admin.apikeys.store') }}" method="POST" class="space-y-4">
                            @csrf

                            <!-- Nama Client -->
                            <div>
                                <label for="name" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Nama Client / Aplikasi <span class="text-rose-500">*</span>
                                </label>
                                <input id="name" type="text" name="name" required placeholder="Contoh: Billing ISP Pusat" 
                                    class="w-full text-xs rounded-xl border-slate-300 bg-white text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm py-2 px-3">
                            </div>

                            <!-- Rate Limit -->
                            <div>
                                <label for="rate_limit" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Rate Limit (req/menit) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative rounded-xl shadow-sm">
                                    <input id="rate_limit" type="number" name="rate_limit" value="60" required min="1"
                                        class="w-full text-xs rounded-xl border-slate-300 bg-white text-slate-800 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm py-2 px-3 pr-16">
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-[11px] text-slate-400 font-medium">
                                        rpm
                                    </div>
                                </div>
                            </div>

                            <!-- IP Whitelist -->
                            <div>
                                <label for="ip_whitelist" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    IP Whitelist <span class="text-[11px] text-slate-400 font-normal">(Opsional)</span>
                                </label>
                                <input id="ip_whitelist" type="text" name="ip_whitelist" placeholder="Contoh: 192.168.1.1, 10.16.39.5" 
                                    class="w-full text-xs rounded-xl border-slate-300 bg-white text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm py-2 px-3">
                                <p class="text-[10px] text-slate-400 mt-1">Pisahkan dengan tanda koma jika lebih dari satu IP.</p>
                            </div>

                            <!-- Scopes -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Scope Permissions <span class="text-[11px] text-slate-400 font-normal">(Kosongkan untuk All)</span>
                                </label>
                                <div class="space-y-2 bg-slate-50/70 p-3 rounded-xl border border-slate-200/80">
                                    <label class="flex items-start gap-2.5 p-1.5 hover:bg-white rounded-lg transition cursor-pointer">
                                        <input type="checkbox" name="scopes[]" value="nas:manage" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-semibold text-slate-700 block">NAS Management</span>
                                            <span class="text-[10px] text-slate-400">Akses penuh CRUD router & hot-reload</span>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-2.5 p-1.5 hover:bg-white rounded-lg transition cursor-pointer">
                                        <input type="checkbox" name="scopes[]" value="users:manage" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-semibold text-slate-700 block">Users & Groups</span>
                                            <span class="text-[10px] text-slate-400">CRUD user PPPoE, password, dan profil</span>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-2.5 p-1.5 hover:bg-white rounded-lg transition cursor-pointer">
                                        <input type="checkbox" name="scopes[]" value="isolate:execute" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-semibold text-slate-700 block">Isolate & Restore</span>
                                            <span class="text-[10px] text-slate-400">Eksekusi isolir & pemutusan sesi PoD</span>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-2.5 p-1.5 hover:bg-white rounded-lg transition cursor-pointer">
                                        <input type="checkbox" name="scopes[]" value="status:read" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-semibold text-slate-700 block">Status Monitoring</span>
                                            <span class="text-[10px] text-slate-400">Melihat user online dan statistik NAS</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Expired Date -->
                            <div>
                                <label for="expires_at" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Tanggal Kadaluarsa <span class="text-[11px] text-slate-400 font-normal">(Opsional)</span>
                                </label>
                                <input id="expires_at" type="date" name="expires_at" 
                                    class="w-full text-xs rounded-xl border-slate-300 bg-white text-slate-800 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm py-2 px-3">
                            </div>

                            <button type="submit" 
                                class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <span>Generate Key Baru</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tables Section (8 Cols) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Daftar API Keys Table -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-md">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Daftar API Key Terdaftar</h3>
                                    <p class="text-[11px] text-slate-500">Kelola dan rotasi kredensial akses aktif</p>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg">
                                Total: {{ $totalKeys }} Key
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="bg-slate-50/80 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th class="py-3 px-4">Client / Aplikasi</th>
                                        <th class="py-3 px-4">Key ID / Prefix</th>
                                        <th class="py-3 px-4">Scopes</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($apiKeys as $k)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-900 text-xs">{{ $k->name }}</div>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="inline-flex items-center text-[10px] text-slate-500 font-medium">
                                                    Rate Limit: <strong class="ml-1 text-slate-700">{{ $k->rate_limit }} rpm</strong>
                                                </span>
                                                @if($k->ip_whitelist)
                                                    <span class="inline-flex items-center text-[10px] text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded font-mono">
                                                        IP Whitelisted
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono">
                                            <div class="inline-flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-slate-800 px-2 py-1 rounded-md text-[11px]">
                                                <span>{{ Str::limit($k->key, 18) }}</span>
                                                <button onclick="copyRawText('{{ $k->key }}', this)" title="Salin Key Prefix" type="button" class="text-slate-400 hover:text-slate-700 transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="flex flex-wrap gap-1 max-w-xs">
                                                @forelse($k->scopes as $s)
                                                    <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-medium border border-slate-200">
                                                        {{ $s }}
                                                    </span>
                                                @empty
                                                    <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded text-[10px] font-semibold border border-indigo-100">
                                                        All Access
                                                    </span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            @if($k->is_active)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Aktif
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    Revoked
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- Rotate Key -->
                                                <form action="{{ route('admin.apikeys.regenerate', $k->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin me-rotate key untuk client {{ $k->name }}? Key lama tidak dapat digunakan lagi.')">
                                                    @csrf
                                                    <button type="submit" title="Rotate / Generate Ulang Kunci"
                                                        class="p-1.5 text-slate-600 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 border border-slate-200 rounded-lg transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                    </button>
                                                </form>

                                                <!-- Toggle Status -->
                                                <form action="{{ route('admin.apikeys.toggle', $k->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" title="{{ $k->is_active ? 'Nonaktifkan / Revoke' : 'Aktifkan Kembali' }}"
                                                        class="px-2 py-1 text-[11px] font-semibold {{ $k->is_active ? 'text-amber-700 bg-amber-50 hover:bg-amber-100 border-amber-200' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border-emerald-200' }} border rounded-lg transition">
                                                        {{ $k->is_active ? 'Revoke' : 'Enable' }}
                                                    </button>
                                                </form>

                                                <!-- Delete -->
                                                <form action="{{ route('admin.apikeys.destroy', $k->id) }}" method="POST" onsubmit="return confirm('Hapus API Key {{ $k->name }} secara permanen?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" title="Hapus Permanen"
                                                        class="p-1.5 text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400">
                                            Belum ada API Key yang dibuat. Silakan gunakan form di samping untuk membuat key pertama.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Traffic Monitor Table (Last 30 Requests) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="p-1.5 bg-cyan-50 text-cyan-600 rounded-md">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Traffic Monitor Live</h3>
                                    <p class="text-[11px] text-slate-500">Catatan 30 request API terakhir masuk ke sistem</p>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-slate-400">Auto-Logged</span>
                        </div>

                        <div class="overflow-x-auto max-h-80">
                            <table class="w-full text-left text-xs font-mono text-slate-700">
                                <thead class="bg-slate-50/80 text-slate-500 uppercase font-semibold text-[10px] tracking-wider sticky top-0 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2.5 px-4">Waktu</th>
                                        <th class="py-2.5 px-4">Method</th>
                                        <th class="py-2.5 px-4">Endpoint</th>
                                        <th class="py-2.5 px-4 text-center">Status</th>
                                        <th class="py-2.5 px-4">Client IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($recentLogs as $log)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="py-2.5 px-4 text-[11px] text-slate-500 font-sans whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('d M H:i:s') }}
                                        </td>
                                        <td class="py-2.5 px-4">
                                            @php
                                                $methodColor = match(strtoupper($log->method)) {
                                                    'GET'    => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'POST'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'PUT', 'PATCH' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    'DELETE' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                    default  => 'bg-slate-100 text-slate-700 border-slate-200',
                                                };
                                            @endphp
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $methodColor }}">
                                                {{ $log->method }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 font-bold text-slate-800 text-[11px]">
                                            {{ $log->endpoint }}
                                        </td>
                                        <td class="py-2.5 px-4 text-center">
                                            @php
                                                $statusColor = match(true) {
                                                    $log->response_status >= 200 && $log->response_status < 300 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    $log->response_status >= 400 && $log->response_status < 500 => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    $log->response_status >= 500 => 'bg-rose-50 text-rose-700 border-rose-200',
                                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                                };
                                            @endphp
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold border {{ $statusColor }}">
                                                {{ $log->response_status }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 text-[11px] text-slate-600">
                                            {{ $log->ip_address }}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400 font-sans">
                                            Belum ada aktivitas request API yang tercatat.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- Script Salin Clipboard -->
    <script>
        function copyToClipboard(elementId, btn) {
            const input = document.getElementById(elementId);
            if (!input) return;

            navigator.clipboard.writeText(input.value).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = `
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Tersalin!</span>
                `;
                btn.classList.add('bg-emerald-600');
                btn.classList.remove('bg-amber-600', 'hover:bg-amber-700');

                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('bg-emerald-600');
                    btn.classList.add('bg-amber-600', 'hover:bg-amber-700');
                }, 2500);
            });
        }

        function copyRawText(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const originalSvg = btn.innerHTML;
                btn.innerHTML = `
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                `;
                setTimeout(() => {
                    btn.innerHTML = originalSvg;
                }, 2000);
            });
        }
    </script>
</x-app-layout>