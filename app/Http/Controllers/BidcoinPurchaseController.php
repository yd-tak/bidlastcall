<?php

namespace App\Http\Controllers;

use App\Models\BidcoinPurchase;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BidcoinPurchaseController extends Controller {
    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = "bidcoin_purchase";
    }

    public function index() {
        // ResponseService::noAnyPermissionThenRedirect(['item-list', 'item-update', 'item-delete']);
        return view('bidcoinpurchase.index');
    }

    public function show(Request $request,$id) {
        try {
            // ResponseService::noPermissionThenSendJson('item-list');
            $offset = $request->input('offset', 0);
            $limit = $request->input('limit', 10);
            $sort = $request->input('sort', 'sequence');
            $order = $request->input('order', 'ASC');
            $sql = BidcoinPurchase::select('bidcoin_purchases.*')->with('user','bidcoinpackage');
            if (!empty($request->search)) {
                $sql = $sql->search($request->search);
            }

            if (!empty($request->filter)) {
                $sql = $sql->filter(json_decode($request->filter, false, 512, JSON_THROW_ON_ERROR));
            }

            $total = $sql->count();
            $sql = $sql->orderBy($sort, $order)->skip($offset)->take($limit);
            $result = $sql->get();

            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();

            foreach ($result as $row) {
                
                $tempRow = $row->toArray();
                $operate = '';

                if ($row->status == 'review') {
                    $operate .= BootstrapTableService::editButton(route('bidcoinpurchase.approval', $row->id), true, '#editStatusModal', 'edit-status', $row->id);
                }
                $tempRow['operate'] = $operate;
                $tempRow['operate'] = $operate;

                $rows[] = $tempRow;
            }
            $bulkData['rows'] = $rows;
            return response()->json($bulkData);

        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "BidcoinPurchaseController --> show");
            ResponseService::errorResponse();
        }
    }

    public function updateBidcoinPurchaseApproval(Request $request, $id) {
        try {
            // ResponseService::noPermissionThenSendJson('item-update');
            $bidcoinpurchase = BidcoinPurchase::findOrFail($id);
            $bidcoinpurchase->update([
                ...$request->all()
            ]);
            ResponseService::successResponse('Bidcoin Purchase Status Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'BidcoinPurchaseController ->updateItemApproval');
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    
}
