@extends('layouts.app')

@section('breadcrumb', 'Vendor Library')
@section('page-title', 'Multi-Vendor Device Library')

@section('content')
<div class="row">
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Manufacturers</p>
                            <h5 class="font-weight-bolder mb-0">{{ $stats['manufacturers_count'] }}</h5>
                            <p class="mb-0 text-sm">
                                <span class="text-success font-weight-bolder">{{ $stats['tr069_vendors'] }}</span> TR-069
                            </p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                            <i class="fas fa-industry text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Products</p>
                            <h5 class="font-weight-bolder mb-0">{{ $stats['products_count'] }}</h5>
                            <p class="mb-0 text-sm">
                                <span class="text-info font-weight-bolder">{{ $stats['tr369_vendors'] }}</span> TR-369
                            </p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                            <i class="fas fa-router text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Vendor Quirks</p>
                            <h5 class="font-weight-bolder mb-0">{{ $stats['quirks_count'] }}</h5>
                            <p class="mb-0 text-sm text-warning">Active workarounds</p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                            <i class="fas fa-exclamation-triangle text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-8">
                        <div class="numbers">
                            <p class="text-sm mb-0 text-capitalize font-weight-bold">Templates</p>
                            <h5 class="font-weight-bolder mb-0">{{ $stats['templates_count'] }}</h5>
                            <p class="mb-0 text-sm text-success">Configuration ready</p>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                            <i class="fas fa-file-code text-lg opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Top Manufacturers</h6>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Manufacturer</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Products</th>
                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Protocol</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topManufacturers as $mfr)
                            <tr>
                                <td>
                                    <div class="d-flex px-2 py-1">
                                        <div class="d-flex flex-column justify-content-center">
                                            <h6 class="mb-0 text-sm">{{ $mfr->name }}</h6>
                                            <p class="text-xs text-secondary mb-0">{{ $mfr->country }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">{{ $mfr->products_count }} products</p>
                                </td>
                                <td class="align-middle text-center text-sm">
                                    @if($mfr->tr069_support)
                                        <span class="badge badge-sm bg-gradient-success">TR-069</span>
                                    @endif
                                    @if($mfr->tr369_support)
                                        <span class="badge badge-sm bg-gradient-info">TR-369</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Quick Access</h6>
            </div>
            <div class="card-body p-3">
                <div class="list-group">
                    <a href="{{ route('acs.vendors.manufacturers') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-industry text-primary me-2"></i> Browse Manufacturers
                    </a>
                    <a href="{{ route('acs.vendors.products') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-router text-info me-2"></i> Browse Products & Models
                    </a>
                    <a href="{{ route('acs.vendors.quirks') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i> Vendor Quirks Database
                    </a>
                    <a href="{{ route('acs.vendors.templates') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-code text-success me-2"></i> Configuration Templates
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
