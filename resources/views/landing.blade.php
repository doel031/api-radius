<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RadiusAPI - Modern FreeRADIUS REST API Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100 antialiased font-sans">

    <!-- Header / Navbar -->
    <header class="fixed w-full z-50 bg-slate-900/80 backdrop-blur-md border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-600 rounded-lg text-white">
                    <i class="fa-solid fa-network-wired text-xl"></i>
                </div>
                <span class="text-xl font-bold tracking-tight text-white">Radius<span class="text-indigo-500">API</span></span>
            </div>
            
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
                <a href="#features" class="hover:text-indigo-400 transition">Fitur Utama</a>
                <a href="/docs/api" class="hover:text-indigo-400 transition">API Docs</a>
                <!-- <a href="#pricing" class="hover:text-indigo-400 transition">Harga</a> -->
            </nav>

            <div class="flex items-center gap-4">
                <a href="/login" class="text-sm font-medium text-slate-300 hover:text-white transition">Masuk</a>
                <!-- <a href="/register" class="text-sm font-medium px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition shadow-lg shadow-indigo-500/25">
                    Dapatkan API Key
                </a> -->
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-semibold uppercase tracking-wider mb-6">
            <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span> Built on FreeRADIUS
        </div>
        <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-tight max-w-4xl mx-auto">
            Kelola Server FreeRADIUS Lewat <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400">RESTful API</span> Modern
        </h1>
        <p class="mt-6 text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto">
            Integrasikan manajemen hotspot, PPPoE, kuota internet, dan otentikasi RADIUS ke dalam aplikasi web/mobile Anda secara instan dan aman.
        </p>

        <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="/docs/api" class="w-full sm:w-auto px-8 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl shadow-lg shadow-indigo-600/30 transition">
                Mulai Integrasi API
            </a>
            <a href="#endpoints" class="w-full sm:w-auto px-8 py-3.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl border border-slate-700 transition">
                Lihat Dokumentasi
            </a>
        </div>
    </section>

    <!-- API Code Preview Section -->
    <section id="endpoints" class="py-12 bg-slate-950 border-y border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold text-white tracking-tight">API Sederhana, Responsif, dan Cepat</h2>
                    <p class="mt-4 text-slate-400">
                        Otomatisasi pembuatan NAS, Group dan User secara real-time hanya dengan memanggil endpoint HTTPS standar.
                    </p>
                    <ul class="mt-6 space-y-3">
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-circle-check text-indigo-400"></i> Otentikasi berbasis HMAC
                        </li>
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-circle-check text-indigo-400"></i> Auto-Sync atribut
                        </li>
                        <li class="flex items-center gap-3 text-slate-300">
                            <i class="fa-solid fa-circle-check text-indigo-400"></i> Webhook callback untuk event pemakaian kuota
                        </li>
                    </ul>
                </div>

                <!-- Code Terminal Box -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl">
                    <div class="bg-slate-800/50 px-4 py-3 flex items-center gap-2 border-b border-slate-800">
                        <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span>
                        <span class="text-xs text-slate-400 font-mono ml-2">POST /api/v1/users/create</span>
                    </div>
                    <pre class="p-4 text-sm font-mono text-slate-300 overflow-x-auto">
<span class="text-purple-400">curl</span> -X POST https://radius.gsmnet.co.id/v1/users \
  -H <span class="text-emerald-400">"Authorization: Bearer YOUR_API_TOKEN"</span> \
  -H <span class="text-emerald-400">"Content-Type: application/json"</span> \
  -d <span class="text-amber-300">'{
    "username": "user_pppoe_12",
    "password": "secretpassword",
    "profile": "10Mbps_unlimited",
    "expiration": "2026-12-31"
  }'</span>
                    </pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto">
            <h2 class="text-3xl font-bold text-white">Fitur Unggulan Engine</h2>
            <p class="mt-3 text-slate-400">Segala hal yang Anda butuhkan untuk mengelola infrastruktur AAA (Authentication, Authorization, Accounting).</p>
        </div>

        <div class="mt-12 grid md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="p-6 bg-slate-800/50 border border-slate-700/50 rounded-2xl hover:border-indigo-500/50 transition">
                <div class="w-12 h-12 bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 rounded-xl flex items-center justify-center text-xl mb-4">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <h3 class="text-xl font-semibold text-white">Manajemen User CRUD</h3>
                <p class="mt-2 text-slate-400 text-sm">Tambah, edit, isolir, atau hapus user RADIUS secara dinamis via REST endpoint.</p>
            </div>

            <!-- Feature 2 -->
            <div class="p-6 bg-slate-800/50 border border-slate-700/50 rounded-2xl hover:border-indigo-500/50 transition">
                <div class="w-12 h-12 bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 rounded-xl flex items-center justify-center text-xl mb-4">
                    <i class="fa-solid fa-gauge-high"></i>
                </div>
                <h3 class="text-xl font-semibold text-white">Bandwidth & Profile Control</h3>
                <p class="mt-2 text-slate-400 text-sm">Atur batas kecepatan `Mikrotik-Rate-Limit` dan kuota bytes (`WISPr-Bandwidth-Max-Down`).</p>
            </div>

            <!-- Feature 3 -->
            <div class="p-6 bg-slate-800/50 border border-slate-700/50 rounded-2xl hover:border-indigo-500/50 transition">
                <div class="w-12 h-12 bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 rounded-xl flex items-center justify-center text-xl mb-4">
                    <i class="fa-solid fa-server"></i>
                </div>
                <h3 class="text-xl font-semibold text-white">NAS & Router Manager</h3>
                <p class="mt-2 text-slate-400 text-sm">Daftarkan router Mikrotik / NAS client baru ke dalam tabel `nas` secara otomatis.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-8 bg-slate-950">
        <div class="max-w-7xl mx-auto px-4 text-center text-slate-500 text-sm">
            <p>&copy; {{ date('Y') }} RadiusAPI Engine. Powered by <a href="https://gsmnet.id" target="_blank" class="text-indigo-400 hover:text-indigo-300">GSMNet</a> & <a href="https://freeradius.org" target="_blank" class="text-indigo-400 hover:text-indigo-300">FreeRADIUS</a>.</p>
        </div>
    </footer>

</body>
</html>