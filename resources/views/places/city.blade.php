@extends('layouts.main')

@section('title')
    {{ __('Cities') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div id="filters">
                            <div class="row">
                                <div class="col-12 col-md-4">
                                    <label for="filter_country">{{__("Country")}}</label>
                                    <select class="form-control bootstrap-table-filter-control-country_name" id="filter_country">
                                        <option value="">{{__("All")}}</option>
                                        @foreach($countries as $country)
                                            <option value="{{$country->id}}">{{$country->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="filter_state">{{__("State")}}</label>
                                    <select class="form-control bootstrap-table-filter-control-state_name" id="filter_state">
                                        <option value="">{{__("All")}}</option>
                                        @foreach($states as $state)
                                            <option value="{{$state->id}}">{{$state->name}}</option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <table class="table-borderless table-striped" aria-describedby="mydesc" id="table_list"
                                       data-toggle="table" data-url="{{ route('cities.show',1) }}" data-click-to-select="true"
                                       data-side-pagination="server" data-pagination="true"
                                       data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                       data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                       data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                                       data-responsive="true" data-sort-name="id" data-sort-order="desc"
                                       data-pagination-successively-size="3" data-table="cities" data-status-column="deleted_at"
                                       data-escape="true"
                                       data-filter-control="true"
                                       data-toolbar="#filters"
                                       data-filter-control-container="#filters"
                                       data-show-export="true" data-export-options='{"fileName": "city-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                                       data-mobile-responsive="true">
                                    <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                        <th scope="col" data-field="country_name" data-sortable="true" data-filter-name="country_id" data-filter-control="select" data-filter-data="">{{ __('Country Name') }}</th>
                                        <th scope="col" data-field="state_name" data-sortable="true" data-filter-name="state_id" data-filter-control="select" data-filter-data="">{{ __('State Name') }}</th>
                                        <th scope="col" data-field="country.emoji">{{ __('Flag') }}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
    </section>
@endsection
