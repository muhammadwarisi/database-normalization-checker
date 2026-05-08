@extends('layouts.app')

@section('title', 'Input Ketergantungan Fungsional')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Ketergantungan Fungsional</h1>
            <p class="text-gray-600">
                Ketergantungan di bawah ini dibuat otomatis dari skema Anda. Tinjau dan sesuaikan jika diperlukan sebelum menganalisis.
            </p>
        </div>

        <form method="POST" action="{{ route('analyze') }}" id="fd-form">
            @csrf

            @foreach ($tables as $tableIndex => $table)
                <div class="bg-white rounded-lg shadow border border-gray-200 mb-6 overflow-hidden">

                    {{-- Table header --}}
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $table['name'] }}</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Kunci Utama:
                            @if (!empty($table['pk_columns']))
                                <span class="font-mono text-blue-700">({{ implode(', ', $table['pk_columns']) }})</span>
                            @else
                                <span class="text-red-500">Tidak ada kunci utama yang didefinisikan</span>
                            @endif
                        </p>
                    </div>

                    <div class="p-6">

                        {{-- Kolom sebagai referensi --}}
                        <div class="mb-5">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Kolom Tersedia</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($table['columns'] as $col)
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono
                            {{ $col['pk'] ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $col['name'] }}
                                        @if ($col['pk'])
                                            <span class="ml-1 opacity-60">[PK]</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        {{-- Info badge jika auto-generated --}}
                        @if (!empty($table['suggested_fds']))
                            <div class="mb-4 flex items-start gap-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                <svg class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd" />
                                </svg>
                                <p class="text-xs text-blue-700">
                                    Ketergantungan di bawah ini dibuat otomatis dari pola penamaan kolom. Tambahkan, hapus, atau ubah jika tidak sesuai dengan desain skema Anda yang sebenarnya.
                                </p>
                            </div>
                        @endif

                        {{-- FD rows --}}
                        <div id="fd-list-{{ $tableIndex }}" class="space-y-3">
                            @forelse($table['suggested_fds'] as $fdIndex => $suggestedFd)
                                <div class="fd-row flex items-start gap-3">
                                    {{-- LHS checkboxes --}}
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">LHS — penentu</label>
                                        <div
                                            class="flex flex-wrap gap-2 p-3 border border-gray-300 rounded-md bg-gray-50 min-h-[44px]">
                                            @foreach ($table['columns'] as $col)
                                                <label class="inline-flex items-center gap-1 cursor-pointer">
                                                    <input type="checkbox"
                                                        name="fds[{{ $table['name'] }}][{{ $fdIndex }}][lhs][]"
                                                        value="{{ $col['name'] }}"
                                                        class="rounded border-gray-300 text-blue-600"
                                                        {{ in_array($col['name'], $suggestedFd['lhs']) ? 'checked' : '' }}>
                                                    <span
                                                        class="text-sm font-mono {{ $col['pk'] ? 'text-blue-700 font-medium' : 'text-gray-700' }}">
                                                        {{ $col['name'] }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="flex items-center pt-7">
                                        <span class="text-gray-400 font-mono text-lg">→</span>
                                    </div>

                                    {{-- RHS select --}}
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">RHS — dependen</label>
                                        <select name="fds[{{ $table['name'] }}][{{ $fdIndex }}][rhs]"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">-- pilih --</option>
                                            @foreach ($table['columns'] as $col)
                                                @if (!$col['pk'])
                                                    <option value="{{ $col['name'] }}"
                                                        {{ $suggestedFd['rhs'] === $col['name'] ? 'selected' : '' }}>
                                                        {{ $col['name'] }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Hapus row --}}
                                    <div class="flex items-center pt-7">
                                        <button type="button" onclick="removeFdRow(this)"
                                            class="p-1.5 text-red-400 hover:text-red-600 rounded hover:bg-red-50"
                                            title="Remove">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                {{-- Tidak ada suggested FD: tampilkan satu row kosong --}}
                                <div class="fd-row flex items-start gap-3">
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">LHS — determinant</label>
                                        <div
                                            class="flex flex-wrap gap-2 p-3 border border-gray-300 rounded-md bg-gray-50 min-h-[44px]">
                                            @foreach ($table['columns'] as $col)
                                                <label class="inline-flex items-center gap-1 cursor-pointer">
                                                    <input type="checkbox" name="fds[{{ $table['name'] }}][0][lhs][]"
                                                        value="{{ $col['name'] }}"
                                                        class="rounded border-gray-300 text-blue-600">
                                                    <span
                                                        class="text-sm font-mono {{ $col['pk'] ? 'text-blue-700 font-medium' : 'text-gray-700' }}">
                                                        {{ $col['name'] }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="flex items-center pt-7">
                                        <span class="text-gray-400 font-mono text-lg">→</span>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">RHS — dependent</label>
                                        <select name="fds[{{ $table['name'] }}][0][rhs]"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                            <option value="">-- select --</option>
                                            @foreach ($table['columns'] as $col)
                                                @if (!$col['pk'])
                                                    <option value="{{ $col['name'] }}">{{ $col['name'] }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-center pt-7">
                                        <button type="button" onclick="removeFdRow(this)"
                                            class="p-1.5 text-red-400 hover:text-red-600 rounded hover:bg-red-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endforelse
                        </div>

                        {{-- Tambah FD --}}
                        <button type="button"
                            onclick="addFdRow({{ $tableIndex }}, '{{ $table['name'] }}', {{ json_encode($table['columns']) }})"
                            class="mt-4 inline-flex items-center px-4 py-2 border border-dashed border-blue-400 text-sm font-medium rounded-md text-blue-600 hover:bg-blue-50">
                            + Tambah Ketergantungan
                        </button>

                    </div>
                </div>
            @endforeach

            <div class="flex items-center justify-between mt-6">
                <a href="{{ route('upload') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                    ← Kembali
                </a>
                <button type="submit"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    Analisis Normalisasi
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            const fdCounters = {};

            function getCurrentCount(tableIndex) {
                return document.querySelectorAll(`#fd-list-${tableIndex} .fd-row`).length;
            }

            function addFdRow(tableIndex, tableName, columns) {
                const idx = getCurrentCount(tableIndex);
                const list = document.getElementById(`fd-list-${tableIndex}`);

                const checkboxes = columns.map(col => `
        <label class="inline-flex items-center gap-1 cursor-pointer">
            <input type="checkbox"
                name="fds[${tableName}][${idx}][lhs][]"
                value="${col.name}"
                class="rounded border-gray-300 text-blue-600">
            <span class="text-sm font-mono ${col.pk ? 'text-blue-700 font-medium' : 'text-gray-700'}">
                ${col.name}
            </span>
        </label>
    `).join('');

                const options = columns
                    .filter(col => !col.pk)
                    .map(col => `<option value="${col.name}">${col.name}</option>`)
                    .join('');

                const row = document.createElement('div');
                row.className = 'fd-row flex items-start gap-3';
                row.innerHTML = `
        <div class="flex-1">
            <label class="block text-xs text-gray-500 mb-1">LHS — determinant</label>
            <div class="flex flex-wrap gap-2 p-3 border border-gray-300 rounded-md bg-gray-50 min-h-[44px]">
                ${checkboxes}
            </div>
        </div>
        <div class="flex items-center pt-7">
            <span class="text-gray-400 font-mono text-lg">→</span>
        </div>
        <div class="flex-1">
            <label class="block text-xs text-gray-500 mb-1">RHS — dependent</label>
            <select name="fds[${tableName}][${idx}][rhs]"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">-- select --</option>
                ${options}
            </select>
        </div>
        <div class="flex items-center pt-7">
            <button type="button" onclick="removeFdRow(this)"
                class="p-1.5 text-red-400 hover:text-red-600 rounded hover:bg-red-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    `;

                list.appendChild(row);
            }

            function removeFdRow(btn) {
                const row = btn.closest('.fd-row');
                const list = row.parentElement;
                if (list.querySelectorAll('.fd-row').length > 1) {
                    row.remove();
                }
            }
        </script>
    @endpush
@endsection
