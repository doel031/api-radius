<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-bold text-xl text-black leading-tight">
                {{ __('API Key Management') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 py-1.5 border border-black rounded-md text-xs font-bold text-black bg-white hover:bg-gray-200 shadow-sm transition">
                ← Kembali ke Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8 bg-white min-h-screen text-black">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Alert Notifikasi API Key Baru -->
            @if(session('new_key'))
                <div class="p-4 bg-emerald-100 border border-black rounded-lg shadow-sm text-black">
                    <span class="font-bold text-sm block text-black">⚠️ Simpan API Key Anda Sekarang!</span>
                    <p class="text-xs text-black mt-1">Key ini hanya ditampilkan sekali saat dibuat/diregenerate:</p>
                    <code class="font-mono text-sm block mt-2 p-3 bg-white rounded border border-black select-all font-bold text-black">
                        {{ session('new_key') }}
                    </code>
                </div>
            @endif

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-xl p-6 border border-black text-black">
                    <span class="text-xs font-bold uppercase tracking-wider text-black">Total API Keys</span>
                    <div class="text-3xl font-extrabold mt-1 text-black">{{ $totalKeys }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-xl p-6 border border-black text-black">
                    <span class="text-xs font-bold uppercase tracking-wider text-black">Active Keys</span>
                    <div class="text-3xl font-extrabold mt-1 text-black">{{ $activeKeys }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-xl p-6 border border-black text-black">
                    <span class="text-xs font-bold uppercase tracking-wider text-black">Total Traffic Logged</span>
                    <div class="text-3xl font-extrabold mt-1 text-black">{{ number_format($totalRequests) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Form Buat API Key Baru -->
                <div class="bg-white shadow-sm rounded-xl p-6 border border-black h-fit text-black">
                    <h3 class="text-base font-bold text-black mb-4 pb-2 border-b border-black">Generate API Key Baru</h3>
                    
                    <form action="{{ route('admin.apikeys.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="name" class="block text-xs font-bold text-black mb-1">Nama Client / Aplikasi</label>
                            <input id="name" type="text" name="name" required placeholder="e.g. Mikrotik Billing System" class="w-full text-xs rounded-lg border-black bg-white text-black font-semibold focus:ring-black focus:border-black placeholder-gray-500">
                        </div>

                        <div>
                            <label for="rate_limit" class="block text-xs font-bold text-black mb-1">Rate Limit (req/menit)</label>
                            <input id="rate_limit" type="number" name="rate_limit" value="60" required class="w-full text-xs rounded-lg border-black bg-white text-black font-semibold focus:ring-black focus:border-black">
                        </div>

                        <div>
                            <label for="ip_whitelist" class="block text-xs font-bold text-black mb-1">IP Whitelist (Opsional)</label>
                            <input id="ip_whitelist" type="text" name="ip_whitelist" placeholder="192.168.1.1, 10.16.39.5" class="w-full text-xs rounded-lg border-black bg-white text-black font-semibold focus:ring-black focus:border-black placeholder-gray-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-black mb-2">Scope Permissions</label>
                            <div class="space-y-2 bg-gray-50 p-3 rounded-lg border border-black text-xs text-black">
                                <label class="flex items-center gap-2 m-2 p-2 cursor-pointer">
                                    <input type="checkbox" name="scopes[]" value="nas:manage" class="rounded border-black bg-white text-black focus:ring-black">
                                    <span class="font-bold text-black">NAS (Full Access)</span>
                                </label>
                                <label class="flex items-center gap-2 m-2 p-2 cursor-pointer">
                                    <input type="checkbox" name="scopes[]" value="users:manage" class="rounded border-black bg-white text-black focus:ring-black">
                                    <span class="font-bold text-black">Users (Full Access)</span>
                                </label>
                                <label class="flex items-center gap-2 m-2 p-2 cursor-pointer">
                                    <input type="checkbox" name="scopes[]" value="isolate:execute" class="rounded border-black bg-white text-black focus:ring-black">
                                    <span class="font-bold text-black">Isolate & Restore</span>
                                </label>
                                <label class="flex items-center gap-2 m-2 p-2 cursor-pointer">
                                    <input type="checkbox" name="scopes[]" value="status:read" class="rounded border-black bg-white text-black focus:ring-black">
                                    <span class="font-bold text-black">Read Status Only</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="expires_at" class="block text-xs font-bold text-black mb-1">Tanggal Expire (Opsional)</label>
                            <input id="expires_at" type="date" name="expires_at" class="w-full text-xs rounded-lg border-black bg-white text-black font-semibold focus:ring-black focus:border-black">
                        </div>
                        <br />

                        <button type="submit" class="w-full py-2 px-4 bg-black border border-black text-black font-bold text-xs rounded-lg shadow transition">
                            Generate Key
                        </button>
                    </form>
                </div>

                <!-- Tabel API Keys & Recent Traffic -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Tabel Keys -->
                    <div class="bg-white shadow-sm rounded-xl p-6 border border-black text-black">
                        <h3 class="text-base font-bold text-black mb-4 pb-2 border-b border-black">Daftar API Keys</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-black">
                                <thead class="bg-gray-100 text-black uppercase font-bold border-b border-black">
                                    <tr>
                                        <th class="p-3 text-black">Client</th>
                                        <th class="p-3 text-black">Key Prefix</th>
                                        <th class="p-3 text-black">Scopes</th>
                                        <th class="p-3 text-black">Status</th>
                                        <th class="p-3 text-right text-black">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-black">
                                    @foreach($apiKeys as $k)
                                    <tr class="hover:bg-gray-100 transition">
                                        <td class="p-3 font-bold text-black">
                                            {{ $k->name }}
                                            <span class="block text-[10px] text-black font-normal mt-0.5">Limit: {{ $k->rate_limit }} req/m</span>
                                        </td>
                                        <td class="p-3 font-mono font-bold text-black">
                                            {{ Str::limit($k->key, 16) }}
                                        </td>
                                        <td class="p-3">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($k->scopes as $s)
                                                    <span class="bg-gray-200 text-black px-1.5 py-0.5 rounded border border-black text-[10px] font-bold">{{ $s }}</span>
                                                @empty
                                                    <span class="text-black text-[10px] font-bold">All Access</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            @if($k->is_active)
                                                <span class="bg-emerald-200 text-black px-2 py-0.5 rounded-full text-[10px] font-bold border border-black">Aktif</span>
                                            @else
                                                <span class="bg-rose-200 text-black px-2 py-0.5 rounded-full text-[10px] font-bold border border-black">Revoked</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex justify-end gap-1.5">
                                                <form action="{{ route('admin.apikeys.regenerate', $k->id) }}" method="POST" onsubmit="return confirm('Rotate key ini?')">
                                                    @csrf
                                                    <button class="bg-gray-200 hover:bg-gray-300 text-black border border-black px-2 py-1 rounded text-[10px] font-bold transition">Rotate</button>
                                                </form>
                                                <form action="{{ route('admin.apikeys.toggle', $k->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button class="bg-gray-200 hover:bg-gray-300 text-black border border-black px-2 py-1 rounded text-[10px] font-bold transition">
                                                        {{ $k->is_active ? 'Revoke' : 'Enable' }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.apikeys.destroy', $k->id) }}" method="POST" onsubmit="return confirm('Hapus key?')">
                                                    @csrf @method('DELETE')
                                                    <button class="bg-rose-200 hover:bg-rose-300 text-black border border-black px-2 py-1 rounded text-[10px] font-bold transition">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Traffic Monitor -->
                    <div class="bg-white shadow-sm rounded-xl p-6 border border-black text-black">
                        <h3 class="text-base font-bold text-black mb-4 pb-2 border-b border-black">Traffic Monitor (30 Request Terakhir)</h3>
                        <div class="overflow-x-auto max-h-60">
                            <table class="w-full text-left text-xs font-mono text-black">
                                <thead class="bg-gray-100 text-black uppercase sticky top-0 border-b border-black">
                                    <tr>
                                        <th class="p-2 text-black">Waktu</th>
                                        <th class="p-2 text-black">Method</th>
                                        <th class="p-2 text-black">Endpoint</th>
                                        <th class="p-2 text-black">Status</th>
                                        <th class="p-2 text-black">Client IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-black">
                                    @foreach($recentLogs as $log)
                                    <tr class="hover:bg-gray-100 transition">
                                        <td class="p-2 text-[10px] text-black font-semibold">{{ $log->created_at }}</td>
                                        <td class="p-2 text-black font-extrabold">{{ $log->method }}</td>
                                        <td class="p-2 text-black font-bold">{{ $log->endpoint }}</td>
                                        <td class="p-2">
                                            <span class="font-extrabold text-black">
                                                {{ $log->response_status }}
                                            </span>
                                        </td>
                                        <td class="p-2 text-black font-semibold">{{ $log->ip_address }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div> <br>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>