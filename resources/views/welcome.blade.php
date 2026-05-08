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
                <h3 class="text-lg font-semibold text-gray-900">Pemeriksaan 1NF</h3>
                <p class="text-sm text-gray-500">Nilai Atomic & Kunci Utama</p>
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
                <h3 class="text-lg font-semibold text-gray-900">Pemeriksaan 2NF</h3>
                <p class="text-sm text-gray-500">Tanpa ketergantungan parsial</p>
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
                <h3 class="text-lg font-semibold text-gray-900">Pemeriksaan 3NF</h3>
                <p class="text-sm text-gray-500">Tanpa ketergantungan transitif</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow border border-gray-200 p-6">
    <div class="text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900">Temporary Analysis</h3>
        <p class="text-sm text-gray-500 mt-2">Analysis results are stored in your browser session and will be cleared when you refresh the page or close your browser.</p>
        <p class="text-sm text-gray-500 mt-2">This ensures your database structures are never permanently saved on our servers.</p>
    </div>
</div>
@endsection