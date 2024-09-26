@extends('layouts.main')

@section('title')
    Item Sales
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
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div id="filters">
                            <label for="filter">Sales Status</label>
                            <select class="form-control bootstrap-table-filter-control-status" id="filter" name="filter">
                                <option value="">All</option>
                                <option value="Open Bid">Open Bid</option>
                                <option value="Menunggu Pembayaran">Menunggu Pembayaran</option>
                                <option value="Review Pembayaran">Review Pembayaran</option>
                                <option value="Transfer ke Seller">Transfer ke Seller</option>
                                <option value="Selesai">Selesai</option>
                                <option value="Tidak Terjual">Tidak Terjual</option>
                            </select>
                        </div>
                        <table class="table-borderless table-striped" aria-describedby="mydesc" id="table_list"
                               data-toggle="table" data-click-to-select="true"
                               data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                               data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                               data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"

                               data-responsive="true" data-sort-name="id" data-sort-order="desc"
                               data-pagination-successively-size="3" data-table="items" data-status-column="deleted_at"
                               data-show-export="true" data-export-options='{"fileName": "item-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                               data-mobile-responsive="true" data-filter-control="true" data-filter-control-container="#filters" data-toolbar="#filters">
                            <thead>
                                <th>Item</th>
                                <th>Seller</th>
                                <th>Open Bid</th>
                                <th data-field="status" data-sortable="true" data-filter-control="select" data-filter-data="" data-escape="false" >Status</th>
                                <th>Current Bid</th>
                                <th>Winner</th>
                                <th>Close Price</th>
                                <th>Shipping</th>
                                <th>Buyer Net</th>
                                <th>Buyer Payment</th>
                                <th>Service</th>
                                <th>Seller Net</th>
                                <th>Seller Transfer</th>
                            </thead>
                            <tbody>
                                <?php foreach($items as $row){?>
                                    <tr>
                                        <td><?=$row->name?></td>
                                        <td><?=$row->user->seller_uname?></td>
                                        <td><?=number_format($row->startbid)?></td>
                                        <td><?=$row->statusparsestr?></td>
                                        <?php if($row->item_bid==null){?>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        <?php } else{?>
                                            <td><?=number_format($row->item_bid->bid_price)?></td>
                                            <td><?=$row->buyer_uname?></td>
                                            <td><?=number_format($row->closeprice)?></td>
                                            <td><?=($row->shippingservice==null)?'Belum pilih ongkir':number_format($row->shippingfee)?></td>
                                            <td><?=number_format($row->buyerbillprice)?></td>
                                            <td><?=($row->item_payment==null)?'Buyer belum bayar':"<a href=\"javascript:viewpayment(".$row->item_payment->id.",'".$row->item_payment->img."','".$row->item_payment->status."')\">".($row->item_payment->status=='review'?'Menunggu Review':'Lunas')."</a>"?></td>
                                            <td><?=number_format($row->servicefee)?></td>
                                            <td><?=number_format($row->totalcloseprice)?></td>
                                            <td><?=($row->item_payment==null)?'Buyer belum bayar':($row->item_payment->status=='review'?'Review Pembayaran Dulu':"<a href=\"javascript:viewpaymenttransfer(".$row->item_payment->id.",'".$row->item_payment->imgtransfer."',".$row->item_payment->istransfered.")\">".($row->item_payment->istransfered?'Sudah Transfer':'Belum Transfer'))."</a>"?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div id="viewPaymentModal" class="modal fade" tabindex="-1" role="dialog" 
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">View/Review Payment <span id="view-payment-status"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo Form::open(array('route' => 'item.reviewpayment'));?>
                            @csrf
                            <input type="hidden" name="id" id="view-payment-id">
                            <div class="row" id="view-payment-status-opt">
                                <div class="col-md-12">
                                    <select name="status" class="form-select" id="status" aria-label="status">
                                        <option value="approve">Approve</option>
                                        <option value="reject">Reject</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <img src="#" id="view-payment-img" style="max-height: 400px;">
                            </div>
                            <input type="submit" value="Save" class="btn btn-primary mt-3" id="view-payment-submit">
                        <?php echo Form::close();?>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <div id="viewTransferModal" class="modal fade" tabindex="-1" role="dialog" 
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">View/Review Pencairan Seller <span id="view-transfer-status"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo Form::open(array('route' => 'item.reviewpaymenttransfer','enctype'=>'multipart/form-data'));?>
                            @csrf
                            <input type="hidden" name="id" id="view-transfer-id">
                            <div class="row" id="view-transfer-img-opt">
                                <div class="col-md-12">
                                    <?php echo Form::file('image');?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <img src="#" id="view-transfer-img" style="max-height: 400px;">
                            </div>
                            <input type="submit" value="Save" class="btn btn-primary mt-3" id="view-transfer-submit">
                        <?php echo Form::close();?>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
    </section>
@endsection
@section('js')
    <script>
        function viewpayment(id,img,status) {
            $("#view-payment-status").html(status);
            $("#view-payment-id").val(id);
            if(status=='review'){
                $("#view-payment-status-opt").css("display","block");
                $("#view-payment-submit").css("display","block");
            }
            else{
                $("#view-payment-status-opt").css("display","none");
                $("#view-payment-submit").css("display","none");
            }
            $("#view-payment-img").prop("src",img);
            $('#viewPaymentModal').modal('show');
        }
        function viewpaymenttransfer(id,img,istransfered) {
            $("#view-transfer-id").val(id);
            if(!istransfered){
                $("#view-transfer-status-opt").css("display","block");
                $("#view-transfer-submit").css("display","block");
            }
            else{
                $("#view-transfer-status-opt").css("display","none");
                $("#view-transfer-submit").css("display","none");
            }
            $("#view-transfer-img").prop("src",img);
            $('#viewTransferModal').modal('show');
        }
    </script>
@endsection
