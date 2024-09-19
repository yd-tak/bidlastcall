@extends('layouts.main')
@section('title')
    Create Bidcoin Package
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons">
            <a class="btn btn-primary" href="{{ route('bidcoinpackage.index') }}">< Back to All Package </a>
        </div>
        <div class="row">
            <form action="{{ route('bidcoinpackage.store') }}" method="POST" data-parsley-validate enctype="multipart/form-data">
                @csrf
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">Add Bidcoin Package</div>

                        <div class="card-body mt-3">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="col-md-6 form-group mandatory">
                                        <label for="category_name" class="mandatory form-label">Name</label>
                                        <input type="text" name="name" id="name" class="form-control" data-parsley-required="true">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="col-md-6 form-group mandatory">
                                        <label for="category_slug" class="form-label">Price</label>
                                        <input type="number" name="price" id="price" class="form-control" data-parsley-required="true">
                                    </div>
                                    <div class="col-md-6 form-group mandatory">
                                        <label for="category_slug" class="form-label">Normal Bidcoin</label>
                                        <input type="number" name="normalbidcoin" id="normalbidcoin" class="form-control" data-parsley-required="true">
                                    </div>
                                    <div class="col-md-6 form-group mandatory">
                                        <label for="category_slug" class="form-label">Bonus Bidcoin</label>
                                        <input type="number" name="bonusbidcoin" id="bonusbidcoin" class="form-control" data-parsley-required="true">
                                    </div>
                                </div>

                                
                                <div class="col-md-12">
                                    <label for="description" class="mandatory form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" cols="10" rows="5"></textarea>
                                    <div class="form-check form-switch mt-3">
                                        <input type="hidden" name="status" id="status" value="0">
                                        <input class="form-check-input status-switch" type="checkbox" role="switch" aria-label="status">Active
                                        <label class="form-check-label" for="status"></label>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 text-end">
                        <input type="submit" class="btn btn-primary" value="Save and Back">
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection


