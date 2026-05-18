@extends('layouts.app')

@section('title', 'Analysis Results - ' . $project->name)

@section('content')
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $project->name }}</h1>
                <p class="text-gray-500 text-sm mt-1">Analyzed {{ $project->created_at->diffForHumans() }}</p>
            </div>
            <a href="{{ route('visualize') }}"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Visualize
            </a>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="text-sm text-gray-500 mb-1">Total Tables</div>
            <div class="text-3xl font-bold text-gray-900">{{ $project->tables_count }}</div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="text-sm text-gray-500 mb-1">Fully Normalized</div>
            <div class="text-3xl font-bold text-green-600">{{ $project->passed_tables }}</div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="text-sm text-gray-500 mb-1">Tables with Issues</div>
            <div class="text-3xl font-bold {{ $project->issues_count > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $project->issues_count }}
            </div>
        </div>
    </div>

    {{-- Per-table results --}}
    <div class="space-y-6">
        @foreach ($project->analysis_result['tables'] as $table)
            @php
                $analysis = $table['analysis'];
                $is1NF = $analysis['1NF']['status'] ?? true;
                $is2NF = $analysis['2NF']['status'];
                $is3NF = $analysis['3NF']['status'] ?? null;

                // Badge warna
                if ($is2NF === true && $is3NF === true) {
                    $badgeClass = 'bg-green-100 text-green-800';
                    $badgeText = '✓ Memenuhi 2NF & 3NF';
                } elseif ($is2NF === false) {
                    $badgeClass = 'bg-red-100 text-red-800';
                    $badgeText = '✗ Melanggar 2NF';
                } elseif ($is2NF === true && $is3NF === false) {
                    $badgeClass = 'bg-yellow-100 text-yellow-800';
                    $badgeText = '⚠ Melanggar 3NF';
                } else {
                    $badgeClass = 'bg-gray-100 text-gray-600';
                    $badgeText = '— Dilewati';
                }
            @endphp

            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">

                {{-- Header --}}
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $table['name'] }}</h3>
                        <p class="text-sm text-gray-500">{{ count($table['columns']) }} columns</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $badgeClass }}">
                        {{ $badgeText }}
                    </span>
                </div>

                <div class="p-6">

                    {{-- Status badges 1NF / 2NF / 3NF --}}
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        @foreach ([['label' => '1NF', 'status' => $is1NF, 'msg' => $analysis['1NF']['message'] ?? ''], ['label' => '2NF', 'status' => $is2NF, 'msg' => $analysis['2NF']['message'] ?? ''], ['label' => '3NF', 'status' => $is3NF, 'msg' => $analysis['3NF']['message'] ?? '']] as $nf)
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if ($nf['status'] === true)
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @elseif($nf['status'] === false)
                                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                            <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                            <span class="text-gray-400 font-bold">—</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $nf['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $nf['msg'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Columns table --}}
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Columns</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Attributes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($table['columns'] as $col)
                                        <tr>
                                            <td class="px-4 py-2 font-mono font-medium text-gray-900">{{ $col['name'] }}
                                            </td>
                                            <td class="px-4 py-2 font-mono text-gray-500">{{ $col['type'] }}</td>
                                            <td class="px-4 py-2">
                                                @if ($col['pk'])
                                                    <span
                                                        class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">PK</span>
                                                @endif
                                                @if ($col['unique'])
                                                    <span
                                                        class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-1">UNIQUE</span>
                                                @endif
                                                @if ($col['not_null'])
                                                    <span
                                                        class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">NOT
                                                        NULL</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Candidate keys --}}
                    @if (!empty($analysis['candidate_keys']))
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Candidate Keys</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($analysis['candidate_keys'] as $ck)
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-mono bg-blue-50 text-blue-800 border border-blue-200">
                                        ({{ implode(', ', $ck) }})
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Minimal cover --}}
                    @if (!empty($analysis['minimal_cover']))
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Minimal Cover (Fm)</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($analysis['minimal_cover'] as $fd)
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-mono bg-gray-50 text-gray-700 border border-gray-200">{{ $fd }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Partial deps --}}
                    @if (!empty($analysis['partial_deps']))
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-red-700 mb-2">Partial Dependencies / Fp (melanggar 2NF)
                            </h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($analysis['partial_deps'] as $fd)
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-mono bg-red-50 text-red-700 border border-red-200">{{ $fd }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Transitive deps --}}
                    @if (!empty($analysis['transitive_deps']))
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-orange-700 mb-2">Transitive Dependencies / Ft (melanggar
                                3NF)</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($analysis['transitive_deps'] as $fd)
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-mono bg-orange-50 text-orange-700 border border-orange-200">{{ $fd }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Dekomposisi 2NF --}}
                    @if (!empty($analysis['decomposed_2nf']) && $is2NF === false)
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Rekomendasi Dekomposisi 2NF</h4>
                            <div class="space-y-2">
                                @foreach ($analysis['decomposed_2nf'] as $rel)
                                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-md">
                                        <p class="text-sm font-mono font-medium text-blue-900">
                                            {{ $rel['name'] }}(<span
                                                class="underline">{{ implode(', ', $rel['primary_key']) }}</span>
                                            @if (!empty($rel['non_pk']))
                                                , {{ implode(', ', $rel['non_pk']) }}
                                            @endif)
                                        </p>
                                        @if (!empty($rel['fds']))
                                            <p class="text-xs text-blue-600 mt-1">FDs: {{ implode(' | ', $rel['fds']) }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Dekomposisi 3NF --}}
                    @if (!empty($analysis['decomposed_3nf']) && $is3NF === false)
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Rekomendasi Dekomposisi 3NF</h4>
                            <div class="space-y-2">
                                @foreach ($analysis['decomposed_3nf'] as $rel)
                                    <div class="p-3 bg-green-50 border border-green-200 rounded-md">
                                        <p class="text-sm font-mono font-medium text-green-900">
                                            {{ $rel['name'] }}(<span
                                                class="underline">{{ implode(', ', $rel['primary_key']) }}</span>
                                            @if (!empty($rel['non_pk']))
                                                , {{ implode(', ', $rel['non_pk']) }}
                                            @endif)
                                        </p>
                                        @if (!empty($rel['fds']))
                                            <p class="text-xs text-green-600 mt-1">FDs: {{ implode(' | ', $rel['fds']) }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Recommendations --}}
                    @if (!empty($analysis['recommendations']))
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-yellow-800 mb-3">📋 Rekomendasi</h4>
                            <ul class="space-y-2">
                                @foreach ($analysis['recommendations'] as $rec)
                                    <li class="text-sm text-yellow-700">• {{ $rec }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        <a href="{{ route('upload') }}" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Upload SQL Baru
        </a>
    </div>
@endsection
