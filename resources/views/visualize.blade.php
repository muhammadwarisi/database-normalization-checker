@extends('layouts.app')

@section('title', 'Visualization - ' . $project->name)

@section('content')

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">{{ $project->name }}</h1>
        <p class="text-gray-500 text-sm mt-1">Entity Relationship Diagram (Vis.js)</p>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
        <div id="erd-vis" style="width: 100%; height: 600px; border: 1px solid #ccc;"></div>
    </div>

    <div class="flex items-center justify-between">
        <a href="{{ route('results') }}"
            class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Analysis
        </a>

        <div class="flex items-center space-x-4">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-blue-500 rounded mr-2"></div>
                <span class="text-sm text-gray-600">Tables</span>
            </div>
            <div class="flex items-center">
                <div class="w-12 h-0.5 bg-gray-400 mr-2"></div>
                <span class="text-sm text-gray-600">Relationships</span>
            </div>
        </div>
    </div>

    @push('scripts')
    <link href="https://unpkg.com/vis-network/styles/vis-network.min.css" rel="stylesheet" />
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
        <script>
            (async function() {
                const data = @json($visualizationData);
                console.log('nodes:', data.nodes);
                console.log('edges:', data.edges);
                const nodes = new vis.DataSet(
                    data.nodes.map(table => ({
                        id: table.id,
                        label: `<b>${table.label}</b>\n` + table.fields.map(f => `- ${f}`).join("\n"),
                        shape: "box",
                        color: {
                            background: "#3B82F6",
                            border: "#1E40AF",
                            highlight: {
                                background: "#2563EB",
                                border: "#1D4ED8"
                            }
                        },
                        font: {
                            color: "white",
                            face: "Inter",
                            size: 14,
                            bold: true,
                            multi: true // support multi-line
                        }
                    }))
                );

                const edges = new vis.DataSet(
                    data.edges.map(edge => ({
                        from: edge.from,
                        to: edge.to,
                        label: edge.via,
                        arrows: "to",
                        color: {
                            color: "#9CA3AF"
                        },
                        font: {
                            align: "top",
                            size: 10,
                            color: "#6B7280"
                        },
                        smooth: {
                            type: "cubicBezier",
                            roundness: 0.4
                        }
                    }))
                );

                const container = document.getElementById("erd-vis");

                const network = new vis.Network(container, {
                    nodes,
                    edges
                }, {
                    autoResize: true,
                    layout: {
                        improvedLayout: true
                    },
                    physics: {
                        enabled: false,
                        stabilization: true,
                        solver: "forceAtlas2Based",
                        forceAtlas2Based: {
                            gravitationalConstant: -80,
                            centralGravity: 0.03,
                            springLength: 150,
                            springConstant: 0.15
                        }
                    },
                    edges: {
                        smooth: true
                    },
                    interaction: {
                        hover: true,
                        dragNodes: true,
                        zoomView: true
                    }
                });

            })();
        </script>
    @endpush

@endsection
