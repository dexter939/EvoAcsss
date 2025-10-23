@extends('layouts.app')

@section('breadcrumb', 'Vendor Library')
@section('page-title', 'Configuration Templates Library')

@section('content')
<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>Filters</h6>
                    <a href="{{ route('acs.vendors.templates') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('acs.vendors.templates') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <select name="manufacturer_id" class="form-control">
                                    <option value="">All Manufacturers</option>
                                    @foreach($manufacturers as $mfr)
                                        <option value="{{ $mfr->id }}" {{ request('manufacturer_id') == $mfr->id ? 'selected' : '' }}>
                                            {{ $mfr->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>
                                            {{ ucfirst($cat) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Protocol</label>
                                <select name="protocol" class="form-control">
                                    <option value="">All Protocols</option>
                                    @foreach($protocols as $proto)
                                        <option value="{{ $proto }}" {{ request('protocol') == $proto ? 'selected' : '' }}>
                                            {{ $proto }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Templates Grid -->
<div class="row">
    @forelse($templates as $template)
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">{{ $template->template_name }}</h6>
                        <p class="text-xs text-secondary mb-0">
                            <i class="fas fa-industry me-1"></i>{{ $template->manufacturer->name }}
                            @if($template->product)
                                <br><i class="fas fa-router me-1"></i>{{ $template->product->model_name }}
                            @endif
                        </p>
                    </div>
                    <span class="badge badge-sm bg-gradient-primary">
                        {{ $template->protocol }}
                    </span>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="mb-2">
                    <span class="badge badge-sm bg-gradient-{{ 
                        $template->template_category === 'security' ? 'danger' : 
                        ($template->template_category === 'qos' ? 'warning' : 
                        ($template->template_category === 'wifi' ? 'info' : 'secondary')) 
                    }}">
                        {{ ucfirst($template->template_category) }}
                    </span>
                </div>

                @if($template->description)
                <p class="text-sm mb-3">{{ $template->description }}</p>
                @endif

                <div class="mb-2">
                    <small class="text-muted">
                        <i class="fas fa-download"></i> Used {{ $template->usage_count }} times
                    </small>
                </div>

                @if($template->validation_rules)
                <div class="mb-2">
                    <small class="text-success">
                        <i class="fas fa-check-circle"></i> Has validation rules
                    </small>
                </div>
                @endif

                <div class="mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-outline-info w-100 mb-2" 
                            onclick="showTemplatePreview({{ $template->id }})">
                        <i class="fas fa-eye"></i> Preview Template
                    </button>
                    <button type="button" class="btn btn-sm btn-primary w-100" 
                            onclick="applyTemplate({{ $template->id }})">
                        <i class="fas fa-paper-plane"></i> Apply to Device
                    </button>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No templates found matching your filters.
        </div>
    </div>
    @endforelse
</div>

<!-- Pagination -->
<div class="row mt-4">
    <div class="col-12">
        {{ $templates->appends(request()->query())->links() }}
    </div>
</div>

<!-- Template Preview Modal -->
<div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="templatePreviewContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Apply Template Modal -->
<div class="modal fade" id="applyTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply Configuration Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="applyTemplateForm">
                    <input type="hidden" id="templateIdInput" name="template_id">
                    <div class="form-group mb-3">
                        <label>Select Device(s)</label>
                        <select class="form-control" name="device_ids[]" multiple required>
                            <option value="">Loading devices...</option>
                        </select>
                        <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple devices</small>
                    </div>
                    <div class="form-group mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="dry_run" id="dryRunCheck" checked>
                            <label class="form-check-label" for="dryRunCheck">
                                Dry run (validate only, don't apply)
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitApplyTemplate()">
                    <i class="fas fa-check"></i> Apply Template
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function showTemplatePreview(templateId) {
    const modal = new bootstrap.Modal(document.getElementById('templatePreviewModal'));
    modal.show();
    
    fetch(`/api/v1/vendors/templates/${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const template = data.data;
                let html = `
                    <h6>${template.template_name}</h6>
                    <p class="text-muted">${template.description || 'No description'}</p>
                    <div class="alert alert-secondary">
                        <strong>Protocol:</strong> ${template.protocol}<br>
                        <strong>Category:</strong> ${template.template_category}
                    </div>
                `;
                
                if (template.template_content) {
                    html += `
                        <h6 class="mt-3">Template Content</h6>
                        <pre class="bg-light p-3 rounded"><code>${JSON.stringify(template.template_content, null, 2)}</code></pre>
                    `;
                }
                
                if (template.validation_rules) {
                    html += `
                        <h6 class="mt-3">Validation Rules</h6>
                        <pre class="bg-light p-3 rounded"><code>${JSON.stringify(template.validation_rules, null, 2)}</code></pre>
                    `;
                }
                
                document.getElementById('templatePreviewContent').innerHTML = html;
            }
        })
        .catch(error => {
            document.getElementById('templatePreviewContent').innerHTML = 
                '<div class="alert alert-danger">Error loading template preview</div>';
        });
}

function applyTemplate(templateId) {
    document.getElementById('templateIdInput').value = templateId;
    const modal = new bootstrap.Modal(document.getElementById('applyTemplateModal'));
    modal.show();
    
    fetch('/api/v1/devices')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.querySelector('select[name="device_ids[]"]');
                select.innerHTML = data.data.map(device => 
                    `<option value="${device.id}">${device.serial_number} - ${device.manufacturer || 'Unknown'}</option>`
                ).join('');
            }
        });
}

function submitApplyTemplate() {
    const form = document.getElementById('applyTemplateForm');
    const formData = new FormData(form);
    
    fetch('/api/v1/vendors/templates/apply', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Template applied successfully!');
            bootstrap.Modal.getInstance(document.getElementById('applyTemplateModal')).hide();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error applying template');
    });
}
</script>
@endpush
