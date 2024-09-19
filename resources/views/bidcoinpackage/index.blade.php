@extends('layouts.main')
@section('title')
    Bidcoin Package
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row align-items-center">
            <div class="col-12 col-md-6">
                <h4 class="mb-0">@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-end">
                <a class="btn btn-primary" href="{{ route('bidcoinpackage.create') }}">+ Add Package </a>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="card">

                    <div class="card-body">
                        <div id="toolbar">
                        </div>
                        <table class="table table-borderless table-striped" aria-describedby="mydesc"
                               id="table_list" data-toggle="table" data-url="{{ route('bidcoinpackage.show',1) }}"
                               data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200,500,2000]" data-search="true" data-search-align="right"
                               data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                               data-trim-on-search="false" data-responsive="true" data-sort-name="sequence"
                               data-sort-order="asc" data-pagination-successively-size="3" data-query-params="queryParams"
                               data-escape="true"
                               data-reorderable-rows="true" data-table="bidcoin_packages" data-use-row-attr-func="true" data-mobile-responsive="true"
                               data-show-export="true" data-export-options='{"fileName": "bidcoin-package","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']">
                            <thead class="thead-dark">
                            <tr>
                                <th scope="col" data-field="id" data-align="center" data-sortable="true">ID</th>
                                <th scope="col" data-field="name" data-align="center" data-sortable="true">Name</th>
                                <th scope="col" data-field="price" data-align="center">Price</th>
                                <th scope="col" data-field="bidcoin" data-align="center" data-sortable="true">Bidcoin</th>
                                <th scope="col" data-field="description" data-align="center" data-sortable="true" >Description</th>
                                <th scope="col" data-field="status" data-width="5" data-sortable="true"  data-formatter="statusSwitchFormatter">Status</th>
                                <th scope="col" data-field="operate" data-escape="false" data-sortable="false">{{ __('Action') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
