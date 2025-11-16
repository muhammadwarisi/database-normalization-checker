@extends('layouts.app')

@section('title', 'Home - ERD Normalization Checker')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Database Normalization Checker</h1>
    <p class="text-gray-600">Analisis otomatis struktur ERD dengan standar 1NF, 2NF, dan 3NF</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">1NF Check</h3>
                <p class="text-sm text-gray-500">Atomic values & Primary Key</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">2NF Check</h3>
                <p class="text-sm text-gray-500">No partial dependencies</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-900">3NF Check</h3>
                <p class="text-sm text-gray-500">No transitive dependencies</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Recent Projects</h2>
    </div>
    <div class="divide-y divide-gray-200">
        @forelse($projects as $project)
        <div class="px-6 py-4 hover:bg-gray-50 transition">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="text-lg font-medium text-gray-900">{{ $project->name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $project->short_summary }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $project->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex items-center space-x-2">
                    @if($project->issues_count === 0)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ✓ Normalized
                    </span>
                    @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        {{ $project->issues_count }} Issues
                    </span>
                    @endif
                    <a href="{{ route('results', $project->project_id) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        View Details
                    </a>
                </div>
            </div>
        </div>
        @empty
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No projects yet</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by analyzing your first DBML file.</p>
            <div class="mt-6">
                <a href="{{ route('upload') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    Start Analysis
                </a>
            </div>
        </div>
        @endforelse
    </div>
</div>
@endsection