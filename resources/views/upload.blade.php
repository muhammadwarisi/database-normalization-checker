@extends('layouts.app')

@section('title', 'Upload DBML - ERD Normalization Checker')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Analyze DBML Structure</h1>
            <p class="text-gray-600">Paste your DBML code below to check normalization compliance</p>
        </div>

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <ul class="mt-1 text-sm text-red-700 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <form method="POST" action="{{ route('parse') }}">
                @csrf

                <div class="mb-6">
                    <label for="project_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Project Name
                    </label>
                    <input type="text" id="project_name" name="project_name" required value="{{ old('project_name') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        placeholder="e.g., Admin Dashboard ERD">
                </div>

                <div class="mb-6">
                    <label for="dbml_text" class="block text-sm font-medium text-gray-700 mb-2">
                        DBML Code
                    </label>
                    <textarea id="dbml_text" name="dbml_text" rows="15" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-md font-mono text-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Table users {&#10;  id bigint [pk]&#10;  email varchar(100) [unique]&#10;  name varchar(100)&#10;}">{{ old('dbml_text') }}</textarea>
                    <p class="mt-2 text-sm text-gray-500">Paste your DBML schema definition here</p>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                        ← Back to Projects
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Next: Input Dependencies →
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-3">DBML Example:</h3>
            <pre class="text-sm text-blue-800 font-mono overflow-x-auto"><code>Table users {
  id bigint [pk]
  email varchar(100) [unique]
  name varchar(100)
  created_at timestamp
}

Table orders {
  id bigint [pk]
  user_id bigint
  total decimal(10,2)
  status varchar(50)
}</code></pre>
        </div>
    </div>
@endsection
