@extends('layouts.app')

@section('title', 'Upload DBML - ERD Normalization Checker')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Analyze DBML Structure</h1>
        <p class="text-gray-600">Paste your DBML code below to check normalization compliance</p>
    </div>

    <div id="alert-container"></div>

    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <form id="upload-form">
            <div class="mb-6">
                <label for="project_name" class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
                <input type="text" id="project_name" name="project_name" required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g., Admin Dashboard ERD">
            </div>

            <div class="mb-6">
                <label for="dbml_text" class="block text-sm font-medium text-gray-700 mb-2">DBML Code</label>
                <textarea id="dbml_text" name="dbml_text" rows="15" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-md font-mono text-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Table users {&#10;  id bigint [pk, increment]&#10;  email varchar(100) [unique]&#10;  name varchar(100)&#10;}"></textarea>
                <p class="mt-2 text-sm text-gray-500">Paste your DBML schema definition here</p>
            </div>

            <div class="flex items-center justify-between">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                    ← Back to Projects
                </a>
                <button type="submit" id="analyze-btn"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="hidden animate-spin -ml-1 mr-3 h-5 w-5 text-white" id="loading-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="btn-text">Analyze DBML</span>
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-3">DBML Example:</h3>
        <pre class="text-sm text-blue-800 font-mono overflow-x-auto"><code>Table users {
  id bigint [pk, increment]
  email varchar(100) [unique]
  name varchar(100)
  created_at timestamp
}

Table orders {
  id bigint [pk, increment]
  user_id bigint [ref: > users.id]
  total decimal(10,2)
  status varchar(50)
}</code></pre>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('analyze-btn');
    const btnText = document.getElementById('btn-text');
    const loadingIcon = document.getElementById('loading-icon');
    const alertContainer = document.getElementById('alert-container');
    
    // Show loading
    btn.disabled = true;
    btnText.textContent = 'Analyzing...';
    loadingIcon.classList.remove('hidden');
    alertContainer.innerHTML = '';
    
    const formData = {
        project_name: document.getElementById('project_name').value,
        dbml_text: document.getElementById('dbml_text').value,
    };
    
    try {
        const response = await fetch('/api/upload-dbml', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (response.ok) {
            // Success
            alertContainer.innerHTML = `
                <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">Analysis Complete!</h3>
                            <p class="text-sm text-green-700 mt-1">Found ${data.summary.tables_count} tables with ${data.summary.issues_count} issues.</p>
                        </div>
                    </div>
                </div>
            `;
            
            // Redirect to results
            setTimeout(() => {
                window.location.href = `/results/${data.project_id}`;
            }, 1000);
        } else {
            // Error
            alertContainer.innerHTML = `
                <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Error</h3>
                            <p class="text-sm text-red-700 mt-1">${data.error || 'Failed to analyze DBML'}</p>
                        </div>
                    </div>
                </div>
            `;
            
            btn.disabled = false;
            btnText.textContent = 'Analyze DBML';
            loadingIcon.classList.add('hidden');
        }
    } catch (error) {
        alertContainer.innerHTML = `
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Network Error</h3>
                        <p class="text-sm text-red-700 mt-1">Failed to connect to server</p>
                    </div>
                </div>
            </div>
        `;
        
        btn.disabled = false;
        btnText.textContent = 'Analyze DBML';
        loadingIcon.classList.add('hidden');
    }
});
</script>
@endpush
@endsection