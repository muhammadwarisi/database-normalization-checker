<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class WebController extends Controller
{
    public function index()
    {
        $projects = Project::orderBy('created_at', 'desc')->limit(10)->get();
        return view('welcome', compact('projects'));
    }
    
    public function upload()
    {
        return view('upload');
    }
    
    public function results(string $projectId)
    {
        $project = Project::where('project_id', $projectId)->firstOrFail();
        return view('results', compact('project'));
    }
    
    public function visualize(string $projectId)
    {
        $project = Project::where('project_id', $projectId)->firstOrFail();
        return view('visualize', compact('project'));
    }
}