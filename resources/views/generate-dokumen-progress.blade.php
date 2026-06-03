<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Generate Dokumen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto px-4 py-12">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Generate Dokumen Asesmen</h1>
            <p class="mt-1 text-sm text-gray-500">Progress pemrosesan batch</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <!-- Status Card -->
            <div class="text-center mb-6">
                <div id="status-badge" class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                    <span id="spinner" class="inline-block w-4 h-4 border-2 border-yellow-600 border-t-transparent rounded-full animate-spin"></span>
                    <span id="status-text">Memproses...</span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>Progress</span>
                    <span id="progress-text">0 / 0</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div id="progress-bar" class="bg-red-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div id="stat-total" class="text-2xl font-bold text-gray-900">0</div>
                    <div class="text-sm text-gray-500">Total</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div id="stat-success" class="text-2xl font-bold text-green-600">0</div>
                    <div class="text-sm text-green-500">Berhasil</div>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <div id="stat-failed" class="text-2xl font-bold text-red-600">0</div>
                    <div class="text-sm text-red-500">Gagal</div>
                </div>
            </div>

            <!-- Download Button (hidden until complete) -->
            <div id="download-section" class="text-center hidden">
                <a id="download-link" href="#" class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download Hasil Excel
                </a>
            </div>
        </div>

        <div class="mt-4 text-center">
            <a href="{{ route('generate-dokumen.form') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                &larr; Kembali ke form upload
            </a>
        </div>
    </div>

    <script>
        const batchId = '{{ $batchId }}';
        const pollInterval = 3000; // 3 seconds

        function pollProgress() {
            fetch('/generate-dokumen/api-progress/' + batchId)
                .then(r => r.json())
                .then(data => {
                    updateUI(data);

                    if (data.status === 'completed') {
                        showComplete(data);
                    } else {
                        setTimeout(pollProgress, pollInterval);
                    }
                })
                .catch(() => {
                    // Retry on error
                    setTimeout(pollProgress, pollInterval);
                });
        }

        function updateUI(data) {
            document.getElementById('stat-total').textContent = data.total || 0;
            document.getElementById('stat-success').textContent = data.success || 0;
            document.getElementById('stat-failed').textContent = data.failed || 0;

            const total = data.total || 1;
            const processed = data.processed || 0;
            const pct = Math.round((processed / total) * 100);

            document.getElementById('progress-text').textContent = processed + ' / ' + total;
            document.getElementById('progress-bar').style.width = pct + '%';
        }

        function showComplete(data) {
            document.getElementById('status-badge').className = 'inline-flex items-center gap-2 px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-medium';
            document.getElementById('spinner').classList.add('hidden');
            document.getElementById('status-text').textContent = 'Selesai!';

            document.getElementById('download-section').classList.remove('hidden');
            document.getElementById('download-link').href = '/generate-dokumen/download/' + batchId;
        }

        // Start polling
        pollProgress();
    </script>
</body>
</html>
