<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Dokumen Pra-Asesmen & Asesmen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-10">
            <h1 class="text-2xl font-bold text-gray-900">Generate Dokumen Asesmen</h1>
            <p class="mt-2 text-gray-600">Generate massal dokumen pra-asesmen dan asesmen tanpa ujian</p>
        </div>

        <div class="space-y-6">
            <!-- Card: Download Template -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-900">1. Download Template Excel</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Download template berisi kolom: <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">ID Izin</span>,
                            <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">Noreg Asesor 1</span>,
                            <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">Noreg Asesor 2</span>,
                            <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">Tanggal Uji</span>
                        </p>
                        <form action="{{ route('generate-dokumen.template') }}" method="GET" class="mt-3">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download Template
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card: Upload & Generate -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-900">2. Upload & Generate</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Upload file Excel yang sudah diisi. Sistem akan sync data, generate dokumen PDF, dan mengirimkan hasil dalam bentuk file Excel.
                        </p>
                        <form action="{{ route('generate-dokumen.import') }}" method="POST" enctype="multipart/form-data" class="mt-4">
                            @csrf
                            <div class="flex items-center gap-3 flex-wrap">
                                <label class="relative cursor-pointer">
                                    <input type="file" name="file" accept=".xlsx" required class="sr-only" onchange="updateFileName(this)">
                                    <div class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                        Pilih File Excel
                                    </div>
                                </label>
                                <span id="file-name" class="text-sm text-gray-400">Belum ada file dipilih</span>
                                <button type="submit" class="px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" id="submit-btn">
                                    Generate Dokumen
                                </button>
                            </div>
                            @if ($errors->any())
                                <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    @foreach ($errors->all() as $error)
                                        <p class="text-sm text-red-600">{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif
                            @if (session('error'))
                                <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-sm text-red-600">{{ session('error') }}</p>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card: Info -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-yellow-800">
                        <p class="font-medium mb-1">Informasi:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-yellow-700">
                            <li>Semua asesi akan dinyatakan <strong>KOMPETEN</strong></li>
                            <li>Metode asesmen yang digunakan: <strong>Observasi</strong></li>
                            <li>Proses dapat memakan waktu karena akan melakukan sync data dari SIKI dan generate PDF</li>
                            <li>Hasil generate akan dikembalikan dalam bentuk file Excel berisi URL dokumen</li>
                            <li>QR Code pada dokumen dapat discan untuk verifikasi</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = document.getElementById('file-name');
            const submitBtn = document.getElementById('submit-btn');
            if (input.files.length > 0) {
                fileName.textContent = input.files[0].name;
                fileName.className = 'text-sm text-gray-700 font-medium';
                submitBtn.disabled = false;
            } else {
                fileName.textContent = 'Belum ada file dipilih';
                fileName.className = 'text-sm text-gray-400';
                submitBtn.disabled = true;
            }
        }

        // Show loading state on submit
        document.querySelector('form[action*="generate-dokumen"]')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<span class="inline-block animate-spin">&#9696;</span> Memproses...';
        });
    </script>
</body>
</html>
