<?php

namespace App\Http\Controllers;

use App\Models\BidcoinPackage;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use function compact;
use function view;

class BidcoinPackageController extends Controller {
    public function __construct() {
    }
    public function index() {
        // ResponseService::noAnyPermissionThenRedirect(['category-list', 'category-create', 'category-update', 'category-delete']);
        return view('bidcoinpackage.index');
    }

    public function create(Request $request) {
        // ResponseService::noPermissionThenRedirect('category-create');
        return view('bidcoinpackage.create');
    }

    public function store(Request $request) {
        // ResponseService::noPermissionThenSendJson('category-create');
        $request->validate([
            'name'               => 'required',
            'price' => 'required|integer',
            // 'bidcoin' => 'required|integer',
            'normalbidcoin' => 'required|integer',
            'bonusbidcoin' => 'required|integer',
            'description'        => 'required',
            'status'             => 'required|boolean'
        ]);

        try {
            $data = $request->all();
            // var_dump($data);
            $data['bidcoin']=$data['normalbidcoin']+$data['bonusbidcoin'];
            $bidcoin = BidcoinPackage::create($data);

            ResponseService::successRedirectResponse("Bidcoin Package Added Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse();
        }
    }


    public function show(Request $request, $id) {
        // ResponseService::noPermissionThenSendJson('category-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');
        $sql=BidcoinPackage::select('*');
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($result as $key => $row) {
            $operate = '';
            $operate .= BootstrapTableService::editButton(route('bidcoinpackage.edit', $row->id));
            $operate .= BootstrapTableService::deleteButton(route('bidcoinpackage.destroy', $row->id));
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id) {
        // ResponseService::noPermissionThenRedirect('category-update');
        $bidcoinpackage_data = BidcoinPackage::findOrFail($id);
        
        return view('bidcoinpackage.edit', compact('bidcoinpackage_data'));
    }

    public function update(Request $request, $id) {
        // ResponseService::noPermissionThenSendJson('category-update');
        try {
            $request->validate([
                'name'               => 'required',
                'price' => 'required|integer',
                // 'bidcoin' => 'required|integer',
                'normalbidcoin' => 'required|integer',
                'bonusbidcoin' => 'required|integer',
                'description'        => 'required',
                'status'             => 'required|boolean'
            ]);

            $bidcoinpackage = BidcoinPackage::find($id);

            $data = $request->all();
            $data['bidcoin']=$data['normalbidcoin']+$data['bonusbidcoin'];
            $bidcoinpackage->update($data);

            
            ResponseService::successRedirectResponse("Bidcoin Package Updated Successfully", route('bidcoinpackage.index'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function destroy($id) {
        // ResponseService::noPermissionThenSendJson('category-delete');
        try {
            $bidcoinpackage = BidcoinPackage::find($id);
            if ($bidcoinpackage->delete()) {
                ResponseService::successResponse('Bidcoin Package deleted successfully');
            }
        } catch (QueryException $th) {
            ResponseService::logErrorResponse($th, 'Failed to delete bidcoin package', 'Cannot delete bidcoin package. Remove associated data first.');
            ResponseService::errorResponse('Something Went Wrong');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "BidcoinPackageController -> delete");
            ResponseService::errorResponse('Something Went Wrong');
        }
    }
}
