@extends('layouts.app')

@section('title', 'Edit Website - Git Webhook Manager')
@section('page-title', 'Edit ' . ucfirst($website->project_type) . ' Website')
@section('page-description', 'Update website configuration')

@section('page-actions')
    <a href="{{ route('websites.index', ['type' => $website->project_type]) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Websites
    </a>
@endsection

@section('content')
    @if(in_array(config('app.env'), ['local', 'dev', 'development']))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-exclamation-triangle me-1"></i>Development Mode:</strong>
            Configurations will be saved to <code>storage/server/</code> instead of system directories.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('websites.update', $website) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="project_type" value="{{ $website->project_type }}">
                
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i> Basic Information
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Website Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control @error('name') is-invalid @enderror" 
                                id="name" 
                                name="name" 
                                value="{{ old('name', $website->name) }}" 
                                required
                                placeholder="My Awesome Project"
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="domain" class="form-label">
                                Domain Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control font-monospace @error('domain') is-invalid @enderror" 
                                id="domain" 
                                name="domain" 
                                value="{{ old('domain', $website->domain) }}" 
                                required
                                placeholder="example.com"
                            >
                            @error('domain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-folder me-2"></i> Path Configuration
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="root_path" class="form-label">
                                Website Root Path <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control font-monospace @error('root_path') is-invalid @enderror" 
                                id="root_path" 
                                name="root_path" 
                                value="{{ old('root_path', $website->root_path) }}" 
                                required
                                placeholder="/var/www/example_com"
                            >
                            @error('root_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if($website->project_type === 'php')
                            <div class="mb-3">
                                <label for="working_directory" class="form-label">
                                    Working Directory (Document Root)
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control font-monospace @error('working_directory') is-invalid @enderror" 
                                    id="working_directory" 
                                    name="working_directory" 
                                    value="{{ old('working_directory', $website->working_directory ?? '/') }}" 
                                    placeholder="/ or /public or /public_html"
                                >
                                <div class="form-text">
                                    <strong>Relative path</strong> from root path. Examples: <code>/</code> (root), <code>/public</code>, <code>/public_html</code>
                                    <br>Final path: <code>{{ $website->root_path }}{working_directory}</code>
                                </div>
                                @error('working_directory')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @else
                            <div class="mb-3">
                                <label for="working_directory" class="form-label">
                                    Run opt
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control font-monospace @error('working_directory') is-invalid @enderror" 
                                    id="working_directory" 
                                    name="working_directory" 
                                    value="{{ old('working_directory', $website->working_directory) }}" 
                                    placeholder="start"
                                >
                                <div class="form-text">Startup mode in package.json</div>
                                @error('working_directory')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-code-slash me-2"></i> @if($website->project_type === 'php') PHP @else Node.js @endif Configuration
                    </div>
                    <div class="card-body">
                        @if($website->project_type === 'php')
                            <div class="mb-3">
                                <label for="php_version" class="form-label">
                                    PHP Version
                                </label>
                                <select 
                                    class="form-select @error('php_version') is-invalid @enderror" 
                                    id="php_version" 
                                    name="php_version"
                                >
                                    <option value="">System Default</option>
                                    @foreach($phpVersions as $version)
                                        <option value="{{ $version }}" {{ old('php_version', $website->php_version) === $version ? 'selected' : '' }}>
                                            PHP {{ $version }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('php_version')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @else
                            <div class="mb-3">
                                <label for="node_version" class="form-label">
                                    Node.js Version
                                </label>
                                <select 
                                    class="form-select @error('node_version') is-invalid @enderror" 
                                    id="node_version" 
                                    name="node_version"
                                >
                                    <option value="">System Default</option>
                                    @foreach($nodeVersions as $version)
                                        <option value="{{ $version }}" {{ old('node_version', $website->node_version) === $version ? 'selected' : '' }}>
                                            Node.js {{ $version }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('node_version')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Port -->
                            <div class="mb-3">
                                <label for="port" class="form-label">
                                    Port
                                </label>
                                <input 
                                    type="number" 
                                    class="form-control @error('port') is-invalid @enderror" 
                                    id="port" 
                                    name="port" 
                                    value="{{ old('port', $website->port) }}" 
                                    placeholder="3000"
                                    min="1"
                                    max="65535"
                                >
                                <div class="form-text">Port where your Node.js application will run (Nginx will proxy to this port)</div>
                                @error('port')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-shield-check me-2"></i> Security & Status
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="ssl_enabled" 
                                    name="ssl_enabled"
                                    value="1"
                                    {{ old('ssl_enabled', $website->ssl_enabled) ? 'checked' : '' }}
                                >
                                <label class="form-check-label" for="ssl_enabled">
                                    Enable Let's Encrypt SSL
                                </label>
                            </div>
                            <div class="form-text">Automatically request Let's Encrypt SSL certificate for HTTPS</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="is_active" 
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', $website->is_active) ? 'checked' : '' }}
                                >
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                            <div class="form-text">Mark website as active/inactive</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Update Website
                    </button>
                    <a href="{{ route('websites.index', ['type' => $website->project_type]) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-header">
                    <i class="bi bi-lightbulb me-2"></i> Quick Tips
                </div>
                <div class="card-body">
                    <h6>Configuration Changes</h6>
                    <p class="small">Updating website settings will trigger automatic Nginx configuration redeployment.</p>
                    
                    <h6 class="mt-3">Path Changes</h6>
                    <p class="small">Changing root path or working directory requires redeploying configurations. Make sure the paths exist on the server.</p>

                    <h6 class="mt-3">Version Changes</h6>
                    <p class="small">@if($website->project_type === 'php')Changing PHP version will update the PHP-FPM pool configuration and reload the service.@else Changing Node.js version requires restarting your application via PM2.@endif</p>

                    <h6 class="mt-3">SSL Certificate</h6>
                    <p class="small">Toggle SSL on/off as needed. Use the "Enable SSL" button on the website detail page to request certificates.</p>

                    <h6 class="mt-3">Redeploy</h6>
                    <p class="small">If configurations aren't applying, use the "Redeploy Config" button on the website detail page.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
