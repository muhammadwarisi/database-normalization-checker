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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
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
        <div class="text-sm text-gray-500 mb-1">Passed 2NF</div>
        <div class="text-3xl font-bold text-green-600">{{ $project->passed_tables }}</div>
    </div>
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <div class="text-sm text-gray-500 mb-1">Tables with Issues</div>
        <div class="text-3xl font-bold {{ $project->issues_count > 0 ? 'text-yellow-600' : 'text-green-600' }}">
            {{ $project->issues_count }}
        </div>
    </div>
</div>

{{-- Per-table results --}}
<div class="space-y-6">
    @foreach($project->analysis_result['tables'] as $table)
    @php
        $analysis    = $table['analysis'];
        $is2NF       = $analysis['2NF']['status'];
        $hasIssues   = !$is2NF || $is2NF === null;
    @endphp

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">

        {{-- Table header --}}
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $table['name'] }}</h3>
                <p class="text-sm text-gray-500">{{ count($table['columns']) }} columns</p>
            </div>
            @if($is2NF === true)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    ✓ Memenuhi 2NF
                </span>
            @elseif($is2NF === false)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    ✗ Melanggar 2NF
                </span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                    — Dilewati
                </span>
            @endif
        </div>

        <div class="p-6">

            {{-- Normalization status badges --}}
            <div class="grid grid-cols-2 gap-4 mb-6">
                {{-- 1NF --}}
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">1NF</p>
                        <p class="text-xs text-gray-500">{{ $analysis['1NF']['message'] }}</p>
                    </div>
                </div>

                {{-- 2NF --}}
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if($is2NF === true)
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        @elseif($is2NF === false)
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        @else
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                            <span class="text-gray-400 text-lg font-bold">—</span>
                        </div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">2NF</p>
                        <p class="text-xs text-gray-500">{{ $analysis['2NF']['message'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Columns table --}}
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Columns</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Attributes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($table['columns'] as $column)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900 font-mono">{{ $column['name'] }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 font-mono">{{ $column['type'] }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if($column['pk'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">PK</span>
                                    @endif
                                    @if($column['unique'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-1">UNIQUE</span>
                                    @endif
                                    @if($column['not_null'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">NOT NULL</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Candidate keys --}}
            @if(!empty($analysis['candidate_keys']))
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Candidate Keys</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($analysis['candidate_keys'] as $ck)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono bg-blue-50 text-blue-800 border border-blue-200">
                        ({{ implode(', ', $ck) }})
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Minimal cover --}}
            @if(!empty($analysis['minimal_cover']))
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Minimal Cover (Fm)</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($analysis['minimal_cover'] as $fd)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono bg-gray-50 text-gray-700 border border-gray-200">
                        {{ $fd }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Partial dependencies --}}
            @if(!empty($analysis['partial_deps']))
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-red-700 mb-2">Partial Dependencies (Fp)</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($analysis['partial_deps'] as $fd)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono bg-red-50 text-red-700 border border-red-200">
                        {{ $fd }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Decomposed relations --}}
            @if(!empty($analysis['decomposed_relations']) && $is2NF === false)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Recommended Decomposition (2NF)</h4>
                <div class="space-y-2">
                    @foreach($analysis['decomposed_relations'] as $rel)
                    <div class="p-3 bg-green-50 border border-green-200 rounded-md">
                        <p class="text-sm font-mono font-medium text-green-800">
                            {{ $rel['name'] }}(<span class="underline">{{ implode(', ', $rel['primary_key']) }}</span>, {{ implode(', ', array_diff($rel['attributes'], $rel['primary_key'])) }})
                        </p>
                        @if(!empty($rel['fds']))
                        <p class="text-xs text-green-600 mt-1">
                            FDs: {{ implode(' | ', $rel['fds']) }}
                        </p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Recommendations --}}
            @if(!empty($analysis['recommendations']))
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-yellow-800 mb-3">📋 Recommendations</h4>
                <ul class="space-y-2">
                    @foreach($analysis['recommendations'] as $rec)
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
    <a href="{{ route('home') }}" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Projects
    </a>
</div>
@endsection