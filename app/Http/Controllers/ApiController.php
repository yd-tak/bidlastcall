<?php

namespace App\Http\Controllers;


use App\Http\Resources\ItemCollection;
use App\Models\Area;
use App\Models\BidcoinBalance;
use App\Models\BidcoinPackage;
use App\Models\BidcoinPurchase;
use App\Models\BlockUser;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Chat;
use App\Models\City;
use App\Models\ContactUs;
use App\Models\Country;
use App\Models\CustomField;
use App\Models\Faq;
use App\Models\Favourite;
use App\Models\FeaturedItems;
use App\Models\FeatureSection;
use App\Models\Item;
use App\Models\ItemBid;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\ItemPayment;
use App\Models\ItemOffer;
use App\Models\Language;
use App\Models\Notifications;
use App\Models\Package;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\Pg;
use App\Models\ReportReason;
use App\Models\Setting;
use App\Models\ServieFee;
use App\Models\Slider;
use App\Models\SocialLogin;
use App\Models\State;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserPurchasedPackage;
use App\Models\UserReports;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\NotificationService;
use App\Services\Payment\PaymentService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ApiController extends Controller {

    private string $uploadFolder;
    private $now;
    public function __construct() {
        $this->uploadFolder = 'item_images';
        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
            $this->middleware('auth:sanctum');
        }
        $this->now=date("Y-m-d H:i:s");
        
        
        
    }
    public function checkBlock(){
        if(Auth::check()){
            $user=Auth::user();
            $user=User::where('id',$user->id)->first();
            // var_dump($user);
            if($user->isblocked){
                ResponseService::blockedResponse();
            }
        }
    }
    public function getSystemSettings(Request $request) {
        $this->checkBlock();
        try {
            $settings = Setting::select(['name', 'value', 'type']);

            if (!empty($request->type)) {
                $settings->where('name', $request->type);
            }

            $settings = $settings->get();

            foreach ($settings as $row) {
                if ($row->name == "place_api_key") {
                    /*TODO : Encryption will be done here*/
                    //$tempRow[$row->name] = HelperService::encrypt($row->value);
                    $tempRow[$row->name] = $row->value;
                } else {
                    $tempRow[$row->name] = $row->value;
                }
            }
            $language = CachingService::getLanguages();
            $tempRow['demo_mode'] = config('app.demo_mode');
            $tempRow['languages'] = $language;
            $tempRow['admin'] = User::role('Super Admin')->select('name', 'profile')->first();

            ResponseService::successResponse("Data Fetched Successfully", $tempRow);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getSystemSettings");
            ResponseService::errorResponse();
        }
    }

    public function userSignup(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'type'         => 'required|in:email,google,phone,apple',
                'firebase_id'  => 'required',
                'country_code' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $type = $request->type;
            $firebase_id = $request->firebase_id;
            $socialLogin = SocialLogin::where('firebase_id', $firebase_id)->where('type', $type)->with('user', function ($q) {
                $q->withTrashed();
            })->whereHas('user', function ($q) {
                $q->role('User');
            })->first();
            if (!empty($socialLogin->user->deleted_at)) {
                ResponseService::errorResponse("User is deactivated. Please Contact the administrator");
            }
            if (empty($socialLogin)) {
                DB::beginTransaction();
                if ($request->type == "phone") {
                    $unique['mobile'] = $request->mobile;
                } else {
                    $unique['email'] = $request->email;
                }
                $user = User::updateOrCreate([...$unique], [
                    ...$request->all(),
                    'profile' => $request->hasFile('profile') ? $request->file('profile')->store('user_profile', 'public') : $request->profile,
                ]);
                SocialLogin::updateOrCreate([
                    'type'    => $request->type,
                    'user_id' => $user->id
                ], [
                    'firebase_id' => $request->firebase_id,
                ]);
                $user->syncRoles('User');
                Auth::login($user);
                $auth = User::where('id',$user->id);
                DB::commit();
            } else {
                Auth::login($socialLogin->user);
                $auth = Auth::user();
            }
            if (!$auth->hasRole('User')) {
                ResponseService::errorResponse('Invalid Login Credentials', null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
            }

            if (!empty($request->fcm_id)) {
                UserFcmToken::insertOrIgnore(['user_id' => $auth->id,'fcm_token' => $request->fcm_id,'created_at' => Carbon::now(),'updated_at' => Carbon::now()]);
            }

            if (!empty($request->registration)) {
                //If registration is passed then don't create token
                $token = null;
            } else {
                $token = $auth->createToken($auth->name ?? '')->plainTextToken;
            }

            ResponseService::successResponse('User logged-in successfully', $auth, ['token' => $token]);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> Signup");
            ResponseService::errorResponse();
        }
    }

    public function updateProfile(Request $request) {
        $this->checkBlock();
        try {
            $validator = Validator::make($request->all(), [
                'name'    => 'nullable|string',
                'seller_uname'=>'required|string',
                'buyer_uname'=>'required|string',
                'provinceid'=>'required|string',
                'cityid'=>'required|string',
                'subdistrictid'=>'required|string',
                'profile' => 'nullable|mimes:jpg,jpeg,png|max:4096',
                'email'   => 'nullable|email|unique:users,email,' . Auth::user()->id,
                'mobile'  => 'sometimes|unique:users,mobile,' . Auth::user()->id,
                'fcm_id'  => 'nullable',
                'address' => 'nullable'
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $app_user = Auth::user();
            //Email should not be updated when type is google.
            $data = $app_user->type == "google" ? $request->except('email') : $request->all();

            if ($request->hasFile('profile')) {
                $data['profile'] = FileService::compressAndReplace($request->file('profile'), 'profile', $app_user->getRawOriginal('profile'));
            }


            $app_user->update($data);
            ResponseService::successResponse("Profile Updated Successfully", $app_user);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> updateProfile');
            ResponseService::errorResponse();
        }
    }

    public function getPackage(Request $request) {
        $this->checkBlock();
        $validator = Validator::make($request->toArray(), [
            'platform' => 'nullable|in:android,ios',
            'type'     => 'nullable|in:advertisement,item_listing'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $packages = Package::where('status', 1);

            if (Auth::check()) {
                $packages = $packages->with('user_purchased_packages', function ($q) {
                    $q->onlyActive();
                });
            }

            if (isset($request->platform) && $request->platform == "ios") {
                $packages->whereNotNull('ios_product_id');
            }

            if (!empty($request->type)) {
                $packages = $packages->where('type', $request->type);
            }
            $packages = $packages->orderBy('id', 'ASC')->get();

            $packages->map(function ($item) {
                if (Auth::check()) {
                    $item['is_active'] = count($item->user_purchased_packages) > 0;
                } else {
                    $item['is_active'] = false;
                }
                return $item;
            });
            ResponseService::successResponse('Data Fetched Successfully', $packages);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getPackage");
            ResponseService::errorResponse();
        }
    }

    public function assignFreePackage(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $user = Auth::user();

            $package = Package::where(['final_price' => 0, 'id' => $request->package_id])->firstOrFail();
            $activePackage = UserPurchasedPackage::onlyActive()->where(['package_id' => $request->package_id, 'user_id' => Auth::user()->id])->first();
            if (!empty($activePackage)) {
                ResponseService::errorResponse("You already have purchased this package");
            }
            UserPurchasedPackage::create([
                'user_id'     => $user->id,
                'package_id'  => $request->package_id,
                'start_date'  => Carbon::now(),
                'total_limit' => $package->item_limit == "unlimited" ? null : $package->item_limit,
                'end_date'    => $package->duration == "unlimited" ? null : Carbon::now()->addDays($package->duration)
            ]);
            ResponseService::successResponse('Package Purchased Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> assignFreePackage");
            ResponseService::errorResponse();
        }
    }

    public function getLimits(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'package_type' => 'required|in:item_listing,advertisement',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $user_package = UserPurchasedPackage::onlyActive()->whereHas('package', function ($q) use ($request) {
                $q->where('type', $request->package_type);
            })->count();
            if ($user_package > 0) {
                ResponseService::successResponse("User is allowed to create Item");
            }
            ResponseService::errorResponse("User is not allowed to create Item", $user_package);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getLimits");
            ResponseService::errorResponse();
        }
    }
    public function getBidcoinBalances(Request $request){
        $this->checkBlock();
        $user = Auth::user();
        $balances=DB::table('bidcoin_balances')->where('user_id',$user->id)->orderBy('created_at')->get();
        $curr=0;
        foreach($balances as $row){
            $close=$curr+$row->debit-$row->credit;
            $row->open=$curr;
            $row->close=$close;
            $curr=$close;
        }
        ResponseService::successResponse('Data Fetched Successfully', [
            'balances'=>$balances,
            'currbalance'=>$curr
        ]);
    }
    public function getBidcoinPackages(Request $request){
        $this->checkBlock();
        $packages=DB::table('bidcoin_packages')->where('status',1)->orderBy('id')->get();
        ResponseService::successResponse('Data Fetched Successfully', $packages);
    }
    public function purchaseBidcoin(Request $request){
        $this->checkBlock();
        DB::beginTransaction();
        $user = Auth::user();
        $package=BidcoinPackage::where('id',$request->bidcoin_package_id)->first();
        $img=null;
        if ($request->hasFile('uploadProof')) {
            $img = FileService::compressAndUpload($request->file('uploadProof'), 'bidcoin_purchase');
        }
        
        $purchase=BidcoinPurchase::create([
            'user_id'=>$user->id,
            'bidcoin_package_id'=>$request->bidcoin_package_id,
            'price'=>$package->price,
            'bidcoin'=>$package->bidcoin,
            'status'=>'review',
            'accountname'=>$request->accountname,
            'accountnum'=>$request->accountnum,
            'bank'=>$request->bank,
            'img'=>$img
        ]);

        DB::commit();
        ResponseService::successResponse('Data Inserted Successfully');
    }
    public function writeBid(Request $request){
        $filepath=public_path('items/'.$request->item_id.'.json');
        $item=Item::where('id',$request->item_id)->first();
        $file=fopen($filepath,"w");
        fwrite($file,json_encode([
            'time_limit'=>$item->enddt,
            'last_price'=>$item->startbid,
            'bidder_uname'=>null,
            'status'=>'open'
        ]));
        fclose($file);
        ResponseService::successResponse("Bid Opened", $item);
    }
    private function getOngkir($origin,$destination,$weight){
        $weight=$weight*1000;
        if($weight==0){
            $weight=1000;
        }
        $curl = curl_init();
        $postfields="origin=".$origin."&originType=subdistrict&destination=".$destination."&destinationType=subdistrict&weight=".$weight."&courier=jne:pos:tiki";
        // echo "postfileds = ".$postfields;
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://pro.rajaongkir.com/api/cost",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $postfields,
          CURLOPT_HTTPHEADER => array(
            "content-type: application/x-www-form-urlencoded",
            "key: 6c40c9e500d3c6a07eab54e5a39b978e"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        $ongkir=json_decode($response);
        // var_dump($ongkir);
        $ongkirOpts=['JNE'=>[],'POS'=>[],'TIKI'=>[]];
        if(isset($ongkir->rajaongkir)){
            foreach($ongkir->rajaongkir->results as $tipeongkir){
                $courier=strtoupper($tipeongkir->code);
                if(!empty($tipeongkir->costs)){
                    foreach($tipeongkir->costs as $ongkirrow){

                        $ongkirOpts[$courier][]=(object)[
                            'courier'=>$courier,
                            'service'=>$ongkirrow->service.' - '.$ongkirrow->description,
                            'serviceid'=>$courier.' - '.$ongkirrow->service.' - '.$ongkirrow->description,
                            'cost'=>$ongkirrow->cost[0]->value,
                            'etd'=>$ongkirrow->cost[0]->etd
                        ];
                    }
                }
            }
        }
        return $ongkirOpts;

    }
    public function getItemDetail(Request $request){
        $this->checkBlock();
        try{

            Item::closeItem($request->item_id);
            echo "EXIT";
            $item=Item::with('user:id,seller_uname,subdistrictid','item_bid:id,user_id,bid_amount,bid_price,tipe,created_at','item_payment','category:id,name,image', 'gallery_images:id,image,item_id')->where('id',$request->item_id)->first();
            // var_dump($item);exit;

            if($item==null){
                throw new \Exception("Item not found");
            }

            // $item->bidstatus='open';

            $currbidstatus=$item->bidstatus;
            if($item->item_bid!=null){
                $winner=User::where('id',$item->item_bid->user_id)->first();
                $item->item_bid->winner_uname=$winner->buyer_uname;
                $item->item_bid->winner_address=$winner->address;
                if($item->item_bid->tipe=='buy'){
                    $item->bidstatus='closed';
                }
                $ongkirOpts=$this->getOngkir($item->user->subdistrictid,$winner->subdistrictid,$item->weight);
                $item->ongkirOpts=$ongkirOpts;
            

            }
            // echo "A";exit;
            if($item->bidstatus=='open' && $item->enddt<$this->now){
                $item->bidstatus='closed';
            }
            if($currbidstatus=='open' && $item->bidstatus=='closed'){
                Item::where('id',$item->id)->first()->save(['bidstatus'=>'closed']);
            }
            // exit;
            // var_dump($buyer);exit;
            $itembids=ItemBid::with('user:id,buyer_uname')->where('item_id',$request->item_id)->get();
            // exit;
            $item->bids=$itembids;
            $item=Item::parseStatusSingle($item);
            // var_dump($item);
            ResponseService::successResponse("Item Detail", $item);
        } catch (\Exception $e) {
            // ResponseService::logErrorResponse($e, "API Controller -> bidItem");
            ResponseService::errorResponse($e->getMessage());
        }
    }
    public function getPgs(Request $request){
        $this->checkBlock();
        $pgs=Pg::get();
        ResponseService::successResponse("PG List", $pgs);
    }
    public function sendItem(Request $request){
        $this->checkBlock();
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
                'noresi' => 'required',
                'send_at' => 'required'
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            
            Item::where('id',$request->item_id)->update([
                'noresi'=>$request->noresi,
                'send_at'=>$request->send_at
            ]);
            DB::commit();

            ResponseService::successResponse("Pengiriman diproses", 1);
            
        } catch (\Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse($e->getMessage());
        }
    }
    public function receiveItem(Request $request){
        $this->checkBlock();
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
                'receive_at' => 'required',
                'is_receive_ok' => 'required'
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            
            Item::where('id',$request->item_id)->update([
                'receive_at'=>$request->receive_at,
                'is_receive_ok'=>$request->is_receive_ok
            ]);
            DB::commit();

            ResponseService::successResponse("Penerimaan barang berhasil", 1);
            
        } catch (\Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse($e->getMessage());
        }
    }
    public function payItem(Request $request){
        $this->checkBlock();
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
                'pg_id' => 'required',
                'paymentdate' => 'required',
                'amount' => 'required|integer',
                'accnum' => 'required',
                'shippingservice'=>'required',
                'shippingfee'=>'required',
                'shippingetd'=>'required'
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $user=Auth::user();
            $item=Item::with('user:id,seller_uname','item_bid:id,user_id,bid_amount,bid_price,tipe,created_at','item_payment','category:id,name,image', 'gallery_images:id,image,item_id','item_payment')->where('id',$request->item_id)->first();
            if($item==null){
                throw new \Exception("Item not found");
            }
            if($item->item_bid==null){
                throw new \Exception("This item have no bid winner");
            }
            if($item->item_payment!=null){
                throw new \Exception("This item have pending payment for review / have been paid");
            }
            $winner=User::where('id',$item->item_bid->user_id)->first();
            $winner_bid=$item->item_bid;
            $item->item_bid->winner_uname=$winner->buyer_uname;
            if($item->item_bid->tipe=='buy'){
                $item->bidstatus='closed';
            }
            if($item->bidstatus=='open' && $item->enddt<$this->now){
                $item->bidstatus='closed';
            }
            if($item->bidstatus=='open'){
                throw new \Exception("Bidding is still open");
            }
            if($winner->id!==$user->id){
                throw new \Exception("You are not the bid winner for this item");
            }
            
            $img=null;
            if ($request->hasFile('uploadProof')) {
                $img = FileService::compressAndUpload($request->file('uploadProof'), 'item_payments');
            }
            $totalcloseprice=$request->amount+$request->shippingfee;
            $data = [
                ...$request->all(),
                'item_bid_id'=>$winner_bid->id,
                'status'=>'review',
                'img'=>$img
            ];
            $item_payment=ItemPayment::create($data);
            Item::where('id',$request->item_id)->update([
                'shippingfee'=>$request->shippingfee,
                'shippingetd'=>$request->shippingetd,
                'shippingservice'=>$request->shippingservice,
                'totalcloseprice'=>$totalcloseprice
            ]);
            DB::commit();
            Item::closeItem($request->item_id);
            

            ResponseService::successResponse("Payment Success", $item_payment);
            
        } catch (\Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse($e->getMessage());
        }
    }
    public function setWarningTimeItem(Request $request){
        $now=new \DateTime();
        $now->modify("+100 second");
        DB::beginTransaction();
        // echo $request->item_id;exit;
        Item::where('id',$request->item_id)->update(['enddt'=>$now->format("Y-m-d H:i:s")]);
        // var_dump($itemdb);exit;

        $filepath=public_path('items/'.$request->item_id.'.json');
        $itemfile=file_get_contents($filepath);
        $item=json_decode($itemfile);
        $item->time_limit=$now->format("Y-m-d H:i:s");
        $item->status='open';

        $filepath=public_path('items/'.$request->item_id.'.json');
        $file=fopen($filepath,"w");
        fwrite($file,json_encode($item));
        fclose($file);
        DB::commit();

        ResponseService::successResponse("Set Warning Success", $item);

    }
    public function bidItem(Request $request){
        $this->checkBlock();
        try {
            DB::beginTransaction();
            $filepath=public_path('items/'.$request->item_id.'.json');
            $itemfile=file_get_contents($filepath);
            $item=json_decode($itemfile);
            $user = Auth::user();
            $now=date("Y-m-d H:i:s");
            $timelimitsecond=90;
            $bidtimelimitdt=new \DateTime($item->time_limit);
            $bidtimelimitdt->modify("-$timelimitsecond second");
            $extendlimitdt=$bidtimelimitdt->format("Y-m-d H:i:s");
            if($now>$item->time_limit){
                throw new \Exception("Tidak dapat memasang bid, waktu BID sudah HABIS");
            }
            if($request->bid_price<=$item->last_price){
                throw new \Exception("Tidak dapat memasang bid, harga sudah berubah");
            }
            
            $updateItem=[];
            if($now>$extendlimitdt){
            // if(true){
                $diffSeconds = (new \DateTime())->getTimestamp() - $bidtimelimitdt->getTimestamp();
                $newtimelimitdt=new \DateTime($item->time_limit);
                // $diffSeconds=300;
                $newtimelimitdt->modify("+".$diffSeconds." second");
                $item->time_limit=$newtimelimitdt->format("Y-m-d H:i:s");
                $updateItem['enddt']=$item->time_limit;
            }
            $item->last_price=$request->bid_price;
            $item->bidder_uname=$user->buyer_uname;
            // var_dump($user);exit;
            $itembid=ItemBid::create([
                'user_id'=>$user->id,
                'item_id'=>$request->item_id,
                'bid_amount'=>$request->bid_amount,
                'bid_price'=>$request->bid_price,
                'bid_dt'=>$now,
                'tipe'=>'bid'
            ]);
            $updateItem['winnerbidid']=$itembid->id;
            $itemdb=Item::where('id',$request->item_id)->first();
            if(isset($updateItem['winnerbidid'])){
                $itemdb->winnerbidid=$updateItem['winnerbidid'];
            }
            if(isset($updateItem['enddt'])){
                $itemdb->enddt=$updateItem['enddt'];
            }
            $itemdb->save($updateItem);

            $file=fopen($filepath,"w");
            fwrite($file,json_encode($item));
            fclose($file);
            
            DB::commit();

            ResponseService::successResponse("Bid Success", $item);
            
        } catch (\Exception $e) {
            DB::rollBack();
            // ResponseService::logErrorResponse($e, "API Controller -> bidItem");
            ResponseService::errorResponse($e->getMessage());
        }
    }
    public function buyNow(Request $request){
        $this->checkBlock();
        try {
            DB::beginTransaction();
            $filepath=public_path('items/'.$request->item_id.'.json');
            $itemfile=file_get_contents($filepath);
            $item=json_decode($itemfile);
            $user = Auth::user();
            $now=date("Y-m-d H:i:s");
            $bidtimelimitdt=new \DateTime($item->time_limit);
            $bidtimelimitdt->modify("-1 minute");
            $extendlimitdt=$bidtimelimitdt->format("Y-m-d H:i:s");
            if($now>$item->time_limit){
                throw new \Exception("Tidak dapat Buy Now, waktu BID sudah HABIS");
            }
            if($request->buy_price<=$item->last_price){
                throw new \Exception("Tidak dapat Buy Now, harga bid sudah diatas Buy Now");
            }
            
            $updateItem=[];
            $item->last_price=$request->buy_price;
            $itembid=ItemBid::create([
                'user_id'=>$user->id,
                'item_id'=>$request->item_id,
                'bid_amount'=>$request->buy_price,
                'bid_price'=>$request->buy_price,
                'bid_dt'=>$now,
                'tipe'=>'buy'
            ]);
            $updateItem['winnerbidid']=$itembid->id;
            $itemdb=Item::where('id',$request->item_id)->first();
            if(isset($updateItem['winnerbidid'])){
                $itemdb->winnerbidid=$updateItem['winnerbidid'];
            }
            if(isset($updateItem['enddt'])){
                $itemdb->enddt=$updateItem['enddt'];
            }
            $updateItem['bidstatus']='closed';

            $itemdb->save($updateItem);
            
            $item->status='closed';
            $item->bidder_uname=$user->buyer_uname;
            $file=fopen($filepath,"w");
            fwrite($file,json_encode($item));
            fclose($file);
            DB::commit();
            Item::closeItem($request->item_id);

            ResponseService::successResponse("Bid Success", $item);
            
        } catch (\Exception $e) {
            DB::rollBack();
            // ResponseService::logErrorResponse($e, "API Controller -> bidItem");
            ResponseService::errorResponse($e->getMessage());
        }
    }
    
    public function addItem(Request $request) {
        $this->checkBlock();
        //bidprice, startbidprice, multiplebidprice, startbid, enddate
        try {
            $validator = Validator::make($request->all(), [
                'name'                 => 'required',
                'category_id'          => 'required|integer',
                'price'                => 'required',
                'description'          => 'required',
                'latitude'             => 'required',
                'longitude'            => 'required',
                'address'              => 'required',
                'contact'              => 'numeric|min:10',
                'show_only_to_premium' => 'required|boolean',
                'video_link'           => 'nullable|url',
                'startdt'                 => 'required',
                'enddt'                 => 'required',
                'startbid'              => 'required|integer',
                'minbid'               => 'required|integer',
                // 'gallery_images'       => 'nullable|array|min:1',
                // 'gallery_images.*'     => 'nullable|mimes:jpeg,png,jpg|max:4096',
                // 'image'                => 'required|mimes:jpeg,png,jpg|max:4096',
                'country'              => 'required',
                'state'                => 'nullable',
                'city'                 => 'required',
                'custom_field_files'   => 'nullable|array',
                'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:4096',
                // 'slug'                 => 'required|regex:/^[a-z0-9-]+$/'
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $user = Auth::user();
            $category=Category::where('id',$request->category_id)->first();

            $data = [
                ...$request->all(),
                'ori_enddt'=>$request->enddt,
                'name'       => $request->name, // Store name in uppercase
                // 'slug'       => HelperService::generateUniqueSlug(new Item(), $request->input('slug')),
                'status'     => "review", //review,approve,reject
                'active'     => "deactive", //active/deactive
                'user_id'    => $user->id,
                'cost'       => $category->cost
            ];

            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);
            }
            $item = Item::create($data);

            BidcoinBalance::create([
                'user_id'=>$user->id,
                'debit'=>0,
                'credit'=>$category->cost,
                'trx'=>'api/add-item',
                'trx_id'=>$item->id,
                'notes'=>'Listing '.$request->name
            ]);

            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImages[] = [
                        'image'      => FileService::compressAndUpload($file, $this->uploadFolder),
                        'item_id'    => $item->id,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }

            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                foreach (json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'created_at'      => time(),
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => !empty($file) ? FileService::upload($file, 'custom_fields_files') : '',
                        'created_at'      => time(),
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }

            $result = Item::with('user:id,name,email,mobile,profile', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area')->where('id', $item->id)->get();
            $result = new ItemCollection($result);

            $filepath=public_path('items/'.$item->id.'.json');
            $file=fopen($filepath,"w");
            fwrite($file,json_encode([
                'time_limit'=>$item->enddt,
                'last_price'=>$item->startbid,
                'status'=>'open',
                'bidder_uname'=>null
            ]));
            fclose($file);

            DB::commit();
            ResponseService::successResponse("Item Added Successfully", $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> addItem");
            ResponseService::errorResponse();
        }
    }

    public function getItem(Request $request) {
        $this->checkBlock();
        $validator = Validator::make($request->all(), [
            'limit'         => 'nullable|integer',
            'offset'        => 'nullable|integer',
            'id'            => 'nullable',
            'category_id'   => 'nullable',
            'user_id'       => 'nullable',
            'min_price'     => 'nullable',
            'max_price'     => 'nullable',
            'sort_by'       => 'nullable|in:new-to-old,old-to-new,price-high-to-low,price-low-to-high,popular_items',
            'posted_since'  => 'nullable|in:all-time,today,within-1-week,within-2-week,within-1-month,within-3-month'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $now=date("Y-m-d H:i:s");
            // echo $now;
            $sql = Item::with('user:id,seller_uname,name,email,mobile,profile,created_at', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area:id,name')
                        ->withCount('favourites')
                        ->with('item_bid');
            if (!empty($request->featured_section_id)) {
                if($request->featured_section_id==='1'){
                    $sql->where("bidstatus","open")->where('startdt','<=',$now)->where('enddt','>=',$now)->orderBy('startdt','desc');
                }
                elseif($request->featured_section_id==='2'){
                    $sql->where("bidstatus","open")->where('startdt','<=',$now)->where('enddt','>=',$now)->orderBy('startdt','asc');
                }
            }
            else{
                $sql->when($request->id, function ($sql) use ($request) {
                        $sql->where('id', $request->id);
                    })->when(($request->category_id), function ($sql) use ($request) {
                        $category = Category::where('id', $request->category_id)->with('children')->get();
                        $categoryIDS = HelperService::findAllCategoryIds($category);
                        return $sql->whereIn('category_id', $categoryIDS);
                    })->when((isset($request->min_price) || isset($request->max_price)), function ($sql) use ($request) {
                        $min_price = $request->min_price ?? 0;
                        $max_price = $request->max_price ?? Item::max('price');
                        return $sql->whereBetween('price', [$min_price, $max_price]);
                    })->when($request->posted_since, function ($sql) use ($request) {
                        return match ($request->posted_since) {
                            "today" => $sql->whereDate('created_at', '>=', now()),
                            "within-1-week" => $sql->whereDate('created_at', '>=', now()->subDays(7)),
                            "within-2-week" => $sql->whereDate('created_at', '>=', now()->subDays(14)),
                            "within-1-month" => $sql->whereDate('created_at', '>=', now()->subMonths()),
                            "within-3-month" => $sql->whereDate('created_at', '>=', now()->subMonths(3)),
                            default => $sql
                        };
                    })->when($request->country, function ($sql) use ($request) {
                        return $sql->where('country', $request->country);
                    })->when($request->state, function ($sql) use ($request) {
                        return $sql->where('state', $request->state);
                    })->when($request->city, function ($sql) use ($request) {
                        return $sql->where('city', $request->city);
                    })->when($request->area_id, function ($sql) use ($request) {
                        return $sql->where('area_id', $request->area_id);
                    })->when($request->slug, function ($sql) use ($request) {
                        return $sql->where('slug', $request->slug);
                    });

                // Sort By
                if ($request->sort_by == "new-to-old") {
                    $sql->orderBy('id', 'DESC');
                } elseif ($request->sort_by == "old-to-new") {
                    $sql->orderBy('id', 'ASC');
                } elseif ($request->sort_by == "price-high-to-low") {
                    $sql->orderBy('price', 'DESC');
                } elseif ($request->sort_by == "price-low-to-high") {
                    $sql->orderBy('price', 'ASC');
                } elseif ($request->sort_by == "popular_items") {
                    $sql->orderBy('clicks', 'DESC');
                } else {
                    $sql->orderBy('id', 'DESC');
                }
            }

            if (!empty($request->search)) {
                $sql->search($request->search);
            }

            $sql->where('status', 'approved');

            if (!empty($request->id)) {
                $result = $sql->get();
                if (count($result) == 0) {
                    ResponseService::errorResponse("No item Found");
                }
            } else {
                $result = $sql->paginate();
            }


            // Return success response with the fetched items
            ResponseService::successResponse("Item Fetched Successfully", new ItemCollection($result),['query'=>DB::getQueryLog()]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getItem");
            ResponseService::errorResponse();
        }
    }

    public function updateItem(Request $request) {
        $this->checkBlock();
        $validator = Validator::make($request->all(), [
            'id'                   => 'required',
            'name'                 => 'nullable',
            'slug'                 => 'required_with:name|regex:/^[a-z0-9-]+$/',
            'price'                => 'nullable',
            'description'          => 'nullable',
            'latitude'             => 'nullable',
            'longitude'            => 'nullable',
            'address'              => 'nullable',
            'contact'              => 'nullable',
            'image'                => 'nullable|mimes:jpeg,jpg,png|max:4096',
            'custom_fields'        => 'nullable',
            'custom_field_files'   => 'nullable|array',
            'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:4096',
            'gallery_images'       => 'nullable|array',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::owner()->findOrFail($request->id);

            $data = $request->all();
            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndReplace($request->file('image'), $this->uploadFolder, $item->getRawOriginal('image'));
            }
            $item->update($data);

            //Update Custom Field values for item
            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                foreach (json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            //Add new gallery images
            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImages[] = [
                        'image'      => FileService::compressAndUpload($file, $this->uploadFolder),
                        'item_id'    => $item->id,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }
                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $value = ItemCustomFieldValue::where(['item_id' => $item->id, 'custom_field_id' => $key,])->first();
                    if (!empty($value)) {
                        $file = FileService::replace($file, 'custom_fields_files', $value->getRawOriginal('value'));
                    } else {
                        $file = '';
                    }
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => $file,
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            //Delete gallery images
            if (!empty($request->delete_item_image_id)) {
                $item_ids = explode(',', $request->delete_item_image_id);
                foreach (ItemImages::whereIn('id', $item_ids)->get() as $itemImage) {
                    FileService::delete($itemImage->getRawOriginal('image'));
                    $itemImage->delete();
                }
            }

            $result = Item::with('user:id,seller_uname,name,email,mobile,profile', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area')->where('id', $item->id)->get();
            /*
             * Collection does not support first OR find method's result as of now. It's a part of R&D
             * So currently using this shortcut method
            */
            $result = new ItemCollection($result);
            DB::commit();
            ResponseService::successResponse("Item Fetched Successfully", $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> updateItem");
            ResponseService::errorResponse();
        }
    }

    public function deleteItem(Request $request) {
        $this->checkBlock();
        try {

            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::errorResponse($validator->errors()->first());
            }
            $item = Item::owner()->with('gallery_images')->withTrashed()->findOrFail($request->id);
            FileService::delete($item->getRawOriginal('image'));

            if (count($item->gallery_images) > 0) {
                foreach ($item->gallery_images as $key => $value) {
                    FileService::delete($value->getRawOriginal('image'));
                }
            }

            $item->forceDelete();
            ResponseService::successResponse("Item Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> deleteItem");
            ResponseService::errorResponse();
        }
    }

    public function updateItemStatus(Request $request) {
        $this->checkBlock();
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'status'  => 'required|in:sold out,inactive,active'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::owner()->whereNotIn('status', ['review', 'rejected'])->withTrashed()->findOrFail($request->item_id);
            if ($request->status == "inactive") {
                $item->delete();
            } else if ($request->status == "active") {
                $item->restore();
                $item->update(['status' => 'review']);
            } else {
                $item->update(['status' => $request->status]);
            }
            ResponseService::successResponse('Item Status Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController -> updateItemStatus');
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getCategories(Request $request) {
        $this->checkBlock();
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = Category::withCount(['subcategories' => function ($q) {
                $q->where('status', 1);
            }, 'approved_items'])->with('translations')->where(['status' => 1])->orderBy('sequence', 'ASC')
                ->with(['subcategories'          => function ($query) {
                    $query->where('status', 1)->orderBy('sequence', 'ASC')->with('translations')->withCount(['subcategories' => function ($q) {
                        $q->where('status', 1);
                    }, 'approved_items']); // Order subcategories by 'sequence'
                }, 'subcategories.subcategories' => function ($query) {
                    $query->where('status', 1)->orderBy('sequence', 'ASC')->with('translations')->withCount(['subcategories' => function ($q) {
                        $q->where('status', 1);
                    }, 'approved_items']); // Order subcategories by 'sequence'
                }]);

            if (!empty($request->category_id)) {
                $sql = $sql->where('parent_category_id', $request->category_id);
            } else if (!empty($request->slug)) {
                $parentCategory = Category::where('slug', $request->slug)->firstOrFail();
                $sql = $sql->where('parent_category_id', $parentCategory->id);
            } else {
                $sql = $sql->whereNull('parent_category_id');
            }

            $sql = $sql->paginate();
            ResponseService::successResponse(null, $sql);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }

    public function getParentCategoryTree(Request $request) {
        $this->checkBlock();
        $validator = Validator::make($request->all(), [
            'child_category_id'=>'required|integer',
            'tree'=>'nullable|boolean'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = Category::findOrFail($request->child_category_id)->ancestorsAndSelf()->breadthFirst()->get();
            if($request->tree){
                $sql = $sql->toTree();
            }
            ResponseService::successResponse(null, $sql);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }
    public function getNotificationList() {
        $this->checkBlock();
        try {
            $notifications = Notifications::whereRaw("FIND_IN_SET(" . Auth::user()->id . ",user_id)")->orWhere('send_to', 'all')->orderBy('id', 'DESC')->paginate();
            ResponseService::successResponse("Notification fetched successfully", $notifications);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getNotificationList');
            ResponseService::errorResponse();
        }
    }

    public function getLanguages(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'language_code' => 'required',
                'type'          => 'nullable|in:app,web'
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $language = Language::where('code', $request->language_code)->firstOrFail();
            if ($request->type == "web") {
                $json_file_path = base_path('resources/lang/' . $request->language_code . '_web.json');
            } else {
                $json_file_path = base_path('resources/lang/' . $request->language_code . '_app.json');
            }

            if (!is_file($json_file_path)) {
                ResponseService::errorResponse("Language file not found");
            }

            $json_string = file_get_contents($json_file_path);
            $json_data = json_decode($json_string, false, 512, JSON_THROW_ON_ERROR);

            if ($json_data == null) {
                ResponseService::errorResponse("Invalid JSON format in the language file");
            }
            $language->file_name = $json_data;

            ResponseService::successResponse("Data Fetched Successfully", $language);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getLanguages");
            ResponseService::errorResponse();
        }
    }

    public function appPaymentStatus(Request $request) {
        try {
            $paypalInfo = $request->all();
            if (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "completed") {
                ResponseService::successResponse("Your Package will be activated within 10 Minutes", $paypalInfo['txn_id']);
            } elseif (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "authorized") {
                ResponseService::successResponse("Your Transaction is Completed. Ads wil be credited to your account within 30 minutes.", $paypalInfo);
            } else {
                ResponseService::errorResponse("Payment Cancelled / Declined ", (isset($_GET)) ? $paypalInfo : "");
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> appPaymentStatus");
            ResponseService::errorResponse();
        }
    }

    public function getPaymentSettings() {
        try {
            $result = PaymentConfiguration::select(['currency_code', 'payment_method', 'api_key', 'status'])->where('status', 1)->get();
            $response = [];
            foreach ($result as $payment) {
                $response[$payment->payment_method] = $payment->toArray();
            }
            ResponseService::successResponse("Data Fetched Successfully", $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getPaymentSettings");
            ResponseService::errorResponse();
        }
    }

    public function getCustomFields(Request $request) {
        try {
            $customField = CustomField::whereHas('custom_field_category', function ($q) use ($request) {
                $q->whereIn('category_id', explode(',', $request->input('category_ids')));
            })->where('status', 1)->get();
            ResponseService::successResponse("Data Fetched successfully", $customField);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getCustomFields");
            ResponseService::errorResponse();
        }
    }

    public function makeFeaturedItem(Request $request) {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::commit();
            $user = Auth::user();
            Item::where('status', 'approved')->findOrFail($request->item_id);
            $user_package = UserPurchasedPackage::onlyActive()->where(['user_id' => $user->id])->with('package')
                ->whereHas('package', function ($q) {
                    $q->where(['type' => 'advertisement']);
                })->firstOrFail();

            $featuredItems = FeaturedItems::where(['item_id' => $request->item_id, 'package_id' => $user_package->package_id])->first();
            if (!empty($featuredItems)) {
                ResponseService::errorResponse("Item is already featured");
            }

            ++$user_package->used_limit;
            $user_package->save();

            FeaturedItems::create([
                'item_id'                   => $request->item_id,
                'package_id'                => $user_package->package_id,
                'user_purchased_package_id' => $user_package->id,
                'start_date'                => date('Y-m-d'),
                'end_date'                  => $user_package->end_date
            ]);

            DB::commit();
            ResponseService::successResponse("Featured Item Created Successfully");
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> createAdvertisement");
            ResponseService::errorResponse();
        }
    }

    public function manageFavourite(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $favouriteItem = Favourite::where('user_id', Auth::user()->id)->where('item_id', $request->item_id)->first();
            if (empty($favouriteItem)) {
                $favouriteItem = new Favourite();
                $favouriteItem->user_id = Auth::user()->id;
                $favouriteItem->item_id = $request->item_id;
                $favouriteItem->save();
                ResponseService::successResponse("Item added to Favourite");
            } else {
                $favouriteItem->delete();
                ResponseService::successResponse("Item remove from Favourite");
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> manageFavourite");
            ResponseService::errorResponse();
        }
    }

    public function getFavouriteItem(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $favouriteItemIDS = Favourite::where('user_id', Auth::user()->id)->select('item_id')->pluck('item_id');
            $items = Item::whereIn('id', $favouriteItemIDS)
                ->with('user:id,seller_uname,name,email,mobile,profile', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field')->where('status', '<>', 'sold out')->paginate();

            ResponseService::successResponse("Data Fetched Successfully", new ItemCollection($items));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getFavouriteItem");
            ResponseService::errorResponse();
        }
    }

    public function getSlider() {
        try {
            $rows = Slider::with(['model' => function (MorphTo $morphTo) {
                $morphTo->constrain([Category::class => function ($query) {
                    $query->withCount('subcategories');
                }]);
            }])->get();
            ResponseService::successResponse(null, $rows);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getSlider");
            ResponseService::errorResponse();
        }
    }

    public function getReportReasons(Request $request) {
        try {
            $report_reason = new ReportReason();
            if (!empty($request->id)) {
                $id = $request->id;
                $report_reason->where('id', '=', $id);
            }
            $result = $report_reason->paginate();
            $total = $report_reason->count();
            ResponseService::successResponse("Data Fetched Successfully", $result, ['total' => $total]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getReportReasons");
            ResponseService::errorResponse();
        }
    }

    public function addReports(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'item_id'          => 'required',
                'report_reason_id' => 'required_without:other_message',
                'other_message'    => 'required_without:report_reason_id'
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $user = Auth::user();
            $report_count = UserReports::where('item_id', $request->item_id)->where('user_id', $user->id)->first();
            if ($report_count) {
                ResponseService::errorResponse("Already Reported");
            }
            UserReports::create([
                ...$request->all(),
                'user_id'       => $user->id,
                'other_message' => $request->other_message ?? '',
            ]);
            ResponseService::successResponse("Report Submitted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> addReports");
            ResponseService::errorResponse();
        }
    }

    public function setItemTotalClick(Request $request) {
        try {

            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            Item::findOrFail($request->item_id)->increment('clicks');
            ResponseService::successResponse(null, 'Update Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> setItemTotalClick");
            ResponseService::errorResponse();
        }
    }

    public function getFeaturedSection(Request $request) {
        try {
            $featureSection = FeatureSection::orderBy('sequence', 'ASC');
            if (isset($request->slug)) {
                $featureSection->where('slug', $request->slug);
            }
            $featureSection = $featureSection->get();
            $tempRow = array();
            $rows = array();
            $now=date("Y-m-d H:i:s");

            $recentItems=Item::where('status','approved')->where('bidstatus','open')->take(10)->with('user:id,seller_uname,name,email,mobile,profile', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field')->withCount('favourites')->with('item_bid')->where('startdt','<=',$now)->where('enddt','>=',$now)->orderBy("startdt","desc")->get();
            // $now=date("Y-m-d H:i:s");
            $openItems=Item::where('status','approved')->where('bidstatus','open')->take(10)->with('user:id,seller_uname,name,email,mobile,profile', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field')->withCount('favourites')->with('item_bid')->where('startdt','<=',$now)->where('enddt','>=',$now)->orderBy("startdt","asc")->get();
            
            ResponseService::successResponse("Data Fetched Successfully", [
                [
                    "id"=>1,
                    "title"=>"New Items",
                    "slug"=>"new-item",
                    "sequence"=>1,
                    "filter"=>"",
                    "value"=>"",
                    "style"=>"style_4",
                    "min_price"=>null,
                    "max_price"=>null,
                    "created_at"=>"2024-05-08T12:20:35.000000Z",
                    "updated_at"=>"2024-05-08T12:20:35.000000Z",
                    "description"=>null,
                    "total_data"=>count($recentItems),
                    "section_data"=>$recentItems
                ],
                [
                    "id"=>2,
                    "title"=>"Bidding Now",
                    "slug"=>"bidding-item",
                    "sequence"=>2,
                    "filter"=>"",
                    "value"=>"",
                    "style"=>"style_4",
                    "min_price"=>null,
                    "max_price"=>null,
                    "created_at"=>"2024-05-08T12:20:35.000000Z",
                    "updated_at"=>"2024-05-08T12:20:35.000000Z",
                    "description"=>null,
                    "total_data"=>count($openItems),
                    "section_data"=>$openItems
                ]
            ]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getFeaturedSection");
            ResponseService::errorResponse();
        }
    }

    public function getPaymentIntent(Request $request) {
        $validator = Validator::make($request->all(), [
            'package_id'     => 'required',
            'payment_method' => 'required|in:Stripe,Razorpay,Paystack',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $paymentConfigurations = PaymentConfiguration::where(['status' => 1, 'payment_method' => $request->payment_method])->first();
            if (empty($paymentConfigurations)) {
                ResponseService::errorResponse("Payment is not Enabled");
            }

            $package = Package::whereNot('final_price', 0)->findOrFail($request->package_id);

            $purchasedPackage = UserPurchasedPackage::onlyActive()->where(['user_id' => Auth::user()->id, 'package_id' => $request->package_id])->first();
            if (!empty($purchasedPackage)) {
                ResponseService::errorResponse("You already have purchased this package");
            }
            //Add Payment Data to Payment Transactions Table
            $paymentTransactionData = PaymentTransaction::create([
                'user_id'         => Auth::user()->id,
                'amount'          => $package->final_price,
                'payment_gateway' => ucfirst($request->payment_method),
                'payment_status'  => 'Pending',
                'order_id'        => null
            ]);

            $paymentIntent = PaymentService::create($request->payment_method)->createAndFormatPaymentIntent(round($package->final_price, 2), [
                'payment_transaction_id' => $paymentTransactionData->id,
                'package_id'             => $package->id,
                'user_id'                => Auth::user()->id,
                'email'                  => Auth::user()->email
            ]);

            $paymentTransactionData->update(['order_id' => $paymentIntent['id']]);

            $paymentTransactionData = PaymentTransaction::findOrFail($paymentTransactionData->id);
            // Custom Array to Show as response
            $paymentGatewayDetails = array(
                ...$paymentIntent,
                'payment_transaction_id' => $paymentTransactionData->id,
            );

            DB::commit();
            ResponseService::successResponse("", ["payment_intent" => $paymentGatewayDetails, "payment_transaction" => $paymentTransactionData]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getPaymentTransactions(Request $request) {
        $validator = Validator::make($request->all(), [
            'latest_only' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $paymentTransactions = PaymentTransaction::where('user_id', Auth::user()->id)->orderBy('id', 'DESC');
            if ($request->latest_only) {
                $paymentTransactions->where('created_at', '>', Carbon::now()->subMinutes(30)->toDateTimeString());
            }
            $paymentTransactions = $paymentTransactions->get();

            $paymentTransactions = collect($paymentTransactions)->map(function ($data) {
                if ($data->payment_status == "pending") {
                    try {
                        $paymentIntent = PaymentService::create($data->payment_gateway)->retrievePaymentIntent($data->order_id);
                    } catch (Throwable) {
                        PaymentTransaction::where('id',$data->id)->update(['payment_status' => "failed"]);
                    }

                    if (!empty($paymentIntent) && $paymentIntent['status'] != "pending") {
                        PaymentTransaction::where('id',$data->id)->update(['payment_status' => $paymentIntent['status'] ?? "failed"]);
                    }
                }
                return $data;
            });

            ResponseService::successResponse("Payment Transactions Fetched", $paymentTransactions);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function createItemOffer(Request $request) {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'amount'  => 'required|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::approved()->notOwner()->findOrFail($request->item_id);
            $itemOffer = ItemOffer::create([
                'item_id'   => $request->item_id,
                'buyer_id'  => Auth::user()->id,
                'amount'    => $request->amount,
                'seller_id' => $item->user_id,
            ]);

            $itemOffer = $itemOffer->load('seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,description,price,image');
            ResponseService::successResponse("Item Offer Created Successfully", $itemOffer);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> createItemOffer");
            ResponseService::errorResponse();
        }
    }

    public function getChatList(Request $request) {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:seller,buyer'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //List of Blocked Users by Auth Users
            $authUserBlockList = BlockUser::where('user_id', Auth::user()->id)->pluck('blocked_user_id');

            //List of Other Users which have blocked the Auth User
            $otherUserBlockList = BlockUser::where('blocked_user_id', Auth::user()->id)->pluck('user_id');
            $itemOffer = ItemOffer::with(['seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,description,price,image,status,deleted_at'])->orderBy('id', 'DESC');

            if ($request->type == "seller") {
                $itemOffer = $itemOffer->where('seller_id', Auth::user()->id);
            } elseif ($request->type == "buyer") {
                $itemOffer = $itemOffer->where('buyer_id', Auth::user()->id);
            }
            $itemOffer = $itemOffer->paginate();
            $itemOffer->getCollection()->transform(function ($value) use ($request, $authUserBlockList, $otherUserBlockList) {
                // Your code here
                if ($request->type == "seller") {
                    $userBlocked = $authUserBlockList->contains($value->buyer_id) || $otherUserBlockList->contains($value->seller_id);
                } elseif ($request->type == "buyer") {
                    $userBlocked = $authUserBlockList->contains($value->seller_id) || $otherUserBlockList->contains($value->buyer_id);
                }
                $value->user_blocked = $userBlocked ?? false;
                return $value;
            });
            ResponseService::successResponse("Chat List Fetched Successfully", $itemOffer);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getChatList");
            ResponseService::errorResponse();
        }
    }

    public function sendMessage(Request $request) {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'required|integer',
            'message'       => (!$request->file('file') && !$request->file('audio')) ? "required" : "nullable",
            'file'          => 'nullable|mimes:jpg,jpeg,png|max:4096',
            'audio'         => 'nullable|mimetypes:audio/mpeg,video/mp4,audio/x-wav,text/plain|max:4096',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = Auth::user();
            //List of users that Auth user has blocked
            $authUserBlockList = BlockUser::where('user_id', $user->id)->get();

            //List of Other users that have blocked the Auth user
            $otherUserBlockList = BlockUser::where('blocked_user_id', $user->id)->get();

            $itemOffer = ItemOffer::with('item')->findOrFail($request->item_offer_id);
            if ($itemOffer->seller_id == $user->id) {
                //If Auth user is seller then check if buyer has blocked the user
                $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because You have blocked this user");
                }

                $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because other user has blocked you.");
                }
            } else {
                //If Auth user is seller then check if buyer has blocked the user
                $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because You have blocked this user");
                }

                $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because other user has blocked you.");
                }
            }
            $chat = Chat::create([
                'sender_id'     => Auth::user()->id,
                'item_offer_id' => $request->item_offer_id,
                'message'       => $request->message,
                'file'          => $request->hasFile('file') ? FileService::compressAndUpload($request->file('file'), 'chat') : '',
                'audio'         => $request->hasFile('audio') ? FileService::compressAndUpload($request->file('audio'), 'chat') : '',
            ]);

            if ($itemOffer->seller_id == $user->id) {
                $receiver_id = $itemOffer->buyer_id;
                $userType = "Seller";
            } else {
                $receiver_id = $itemOffer->seller_id;
                $userType = "Buyer";
            }
            $notificationPayload = $chat->toArray();

            $fcmMsg = [
                ...$notificationPayload,
                'user_id'           => $user->id,
                'user_name'         => $user->name,
                'user_profile'      => $user->profile,
                'user_type'         => $userType,
                'item_id'           => $itemOffer->item->id,
                'item_name'         => $itemOffer->item->name,
                'item_image'        => $itemOffer->item->image,
                'item_price'        => $itemOffer->item->price,
                'item_offer_id'     => $itemOffer->id,
                'item_offer_amount' => $itemOffer->amount,
                'type'              => $notificationPayload['message_type']
            ];
            /* message_type is reserved keyword in FCM so removed here*/
            unset($fcmMsg['message_type']);
            $receiverFCMTokens = UserFcmToken::where('user_id', $receiver_id)->pluck('fcm_token')->toArray();
            $notification = NotificationService::sendFcmNotification($receiverFCMTokens, 'Message', $request->message, "chat", $fcmMsg);

            DB::commit();
            ResponseService::successResponse("Message Fetched Successfully", $chat, ['debug' => $notification]);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> sendMessage");
            ResponseService::errorResponse();
        }
    }

    public function getChatMessages(Request $request) {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $itemOffer = ItemOffer::owner()->findOrFail($request->item_offer_id);
            $chat = Chat::where('item_offer_id', $itemOffer->id)->orderBy('created_at', 'DESC')->paginate();

            ResponseService::successResponse("Messages Fetched Successfully", $chat);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getChatMessages");
            ResponseService::errorResponse();
        }
    }

    public function deleteUser() {
        try {
            User::findOrFail(Auth::user()->id)->forceDelete();
            ResponseService::successResponse("User Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> deleteUser");
            ResponseService::errorResponse();
        }
    }

    public function inAppPurchase(Request $request) {
        $validator = Validator::make($request->all(), [
            'purchase_token' => 'required',
            'payment_method' => 'required|in:google,apple',
            'package_id'     => 'required|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $package = Package::findOrFail($request->package_id);
            $purchasedPackage = UserPurchasedPackage::where(['user_id' => Auth::user()->id, 'package_id' => $request->package_id])->first();
            if (!empty($purchasedPackage)) {
                ResponseService::errorResponse("You already have purchased this package");
            }

            PaymentTransaction::create([
                'user_id'         => Auth::user()->id,
                'amount'          => $package->final_price,
                'payment_gateway' => $request->payment_method,
                'order_id'        => $request->purchase_token,
                'payment_status'  => 'success',
            ]);

            UserPurchasedPackage::create([
                'user_id'     => Auth::user()->id,
                'package_id'  => $request->package_id,
                'start_date'  => Carbon::now(),
                'total_limit' => $package->item_limit == "unlimited" ? null : $package->item_limit,
                'end_date'    => $package->duration == "unlimited" ? null : Carbon::now()->addDays($package->duration)
            ]);
            ResponseService::successResponse("Package Purchased Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> inAppPurchase");
            ResponseService::errorResponse();
        }
    }

    public function blockUser(Request $request) {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BlockUser::create([
                'user_id'         => Auth::user()->id,
                'blocked_user_id' => $request->blocked_user_id,
            ]);
            ResponseService::successResponse("User Blocked Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> blockUser");
            ResponseService::errorResponse();
        }
    }

    public function unblockUser(Request $request) {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BlockUser::where([
                'user_id'         => Auth::user()->id,
                'blocked_user_id' => $request->blocked_user_id,
            ])->delete();
            ResponseService::successResponse("User Unblocked Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> unblockUser");
            ResponseService::errorResponse();
        }
    }

    public function getBlockedUsers() {
        try {
            $blockedUsers = BlockUser::where('user_id', Auth::user()->id)->pluck('blocked_user_id');
            $users = User::whereIn('id', $blockedUsers)->select(['id', 'name', 'profile'])->get();
            ResponseService::successResponse("User Unblocked Successfully", $users);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> unblockUser");
            ResponseService::errorResponse();
        }
    }

    public function getTips() {
        try {
            $tips = Tip::select(['id', 'description'])->orderBy('sequence', 'ASC')->with('translations')->get();
            ResponseService::successResponse("Tips Fetched Successfully", $tips);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getTips");
            ResponseService::errorResponse();
        }
    }

    public function getBlog(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'nullable|integer|exists:categories,id',
                'blog_id'     => 'nullable|integer|exists:blogs,id',
                'sort_by'     => 'nullable|in:new-to-old,old-to-new,popular',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $blogs = Blog::when(!empty($request->id), static function ($q) use ($request) {
                $q->where('id', $request->id);
                Blog::where('id', $request->id)->increment('views');
            })
                ->when(!empty($request->slug), function ($q) use ($request) {
                    $q->where('slug', $request->slug);
                    Blog::where('slug', $request->slug)->increment('views');
                })
                ->when(!empty($request->sort_by), function ($q) use ($request) {
                    if ($request->sort_by === 'new-to-old') {
                        $q->orderByDesc('created_at');
                    } elseif ($request->sort_by === 'old-to-new') {
                        $q->orderBy('created_at');
                    } else if ($request->sort_by === 'popular') {
                        $q->orderByDesc('views');
                    }
                })
                ->when(!empty($request->tag), function ($q) use ($request) {
                    $q->where('tags', 'like', "%" . $request->tag . "%");
                })->paginate();

            $otherBlogs = [];
            if (!empty($request->id) || !empty($request->slug)) {
                $otherBlogs = Blog::orderByDesc('id')->limit(3)->get();
            }
            // Return success response with the fetched blogs
            ResponseService::successResponse("Blogs fetched successfully", $blogs, ['other_blogs' => $otherBlogs]);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getBlog');
            ResponseService::errorResponse("Failed to fetch blogs");
        }
    }

    public function getCountries(Request $request) {
        try {
            $searchQuery = $request->search ?? '';
            $countries = Country::search($searchQuery)->orderBy('name', 'ASC')->paginate();
            ResponseService::successResponse("Countries Fetched Successfully", $countries);
        } catch (Throwable $th) {
            // Log and handle any exceptions
            ResponseService::logErrorResponse($th, "API Controller -> getCountries");
            ResponseService::errorResponse("Failed to fetch countries");
        }
    }

    public function getStates(Request $request) {
        $validator = Validator::make($request->all(), [
            'country_id' => 'nullable|integer',
            'search'     => 'nullable'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $searchQuery = $request->search ?? '';
            $states = State::search($searchQuery)->orderBy('name', 'ASC');
            if (isset($request->country_id)) {
                $states->where('country_id', $request->country_id);
            }
            $states = $states->paginate();
            ResponseService::successResponse("States Fetched Successfully", $states);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller->getStates");
        }
    }

    public function getCities(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'state_id' => 'nullable|integer',
                'search'   => 'nullable'
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $searchQuery = $request->search ?? '';
            $cities = City::search($searchQuery)->orderBy('name', 'ASC');
            if (isset($request->state_id)) {
                $cities->where('state_id', $request->state_id);
            }
            $cities = $cities->paginate();
            ResponseService::successResponse("Cities Fetched Successfully", $cities);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller->getCities");
        }
    }

    public function getAreas(Request $request) {
        $validator = Validator::make($request->all(), [
            'city_id' => 'nullable|integer',
            'search'  => 'nullable'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $searchQuery = $request->search ?? '';
            $data = Area::search($searchQuery)->orderBy('name', 'ASC');
            if (isset($request->city_id)) {
                $data->where('city_id', $request->city_id);
            }

            $data = $data->paginate();
            ResponseService::successResponse("Area fetched Successfully", $data);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getAreas');
            ResponseService::errorResponse();
        }
    }

    public function getFaqs() {
        try {
            $faqs = Faq::get();
            ResponseService::successResponse("FAQ Data fetched Successfully", $faqs);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getFaqs');
            ResponseService::errorResponse("Failed to fetch Faqs");
        }
    }

    public function getAllBlogTags() {
        try {
            $tagsArray = [];
            Blog::select('tags')->chunk(100, function ($blogs) use (&$tagsArray) {
                foreach ($blogs as $blog) {
                    foreach ($blog->tags as $tags) {
                        $tagsArray[] = $tags;
                    }
                }
            });
            $tagsArray = array_unique($tagsArray);
            ResponseService::successResponse("Blog Tags Successfully", $tagsArray);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getAllBlogTags');
            ResponseService::errorResponse("Failed to fetch Tags");
        }
    }

    public function storeContactUs(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'    => 'required',
            'email'   => 'required|email',
            'subject' => 'required',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            ContactUs::create($request->all());
            ResponseService::successResponse("Contact Us Stored Successfully");

        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> storeContactUs');
            ResponseService::errorResponse();
        }
    }

    public function getMyAuction(Request $request) {
        $this->checkBlock();
        try {
            $user = Auth::user();
            $now=new \DateTime();
            $sql = Item::selectRaw('items.*,max(ib.bid_price) as my_bid_price,winnerib.bid_price as winner_bid_price')->with('user:id,seller_uname,name,email,mobile,profile,created_at', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area:id,name','item_payment')
            ->join('item_bids as ib','ib.item_id','=','items.id')->where('ib.user_id',$user->id)
            ->leftJoin('item_bids as winnerib','items.winnerbidid','=','winnerib.id')
            ->where('items.bidstatus','open')
            ->orderBy('ib.created_at','desc')
            ->groupBy('items.id')
            ->get();

            $buys=[];
            $close_itemids=[];
            foreach($sql as $row){
                $enddt=new \DateTime($row->enddt);
                if($enddt<$now){
                    $row->bidstatus='closed';
                    $close_itemids[]=$row->id;
                }
                else{
                    $buys[]=$row;
                }
            }

            $sql = Item::select('items.*','winnerib.bid_price as winner_bid_price','winnerib.user_id as winner_id')->with('user:id,seller_uname,name,email,mobile,profile,created_at', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area:id,name','item_payment')
            ->leftJoin('item_bids as winnerib','items.winnerbidid','=','winnerib.id')
            ->where('items.user_id',$user->id)
            ->where('items.bidstatus','open')
            ->orderBy('items.created_at','desc')
            ->get();
            
            foreach($sql as $row){
                $enddt=new \DateTime($row->enddt);
                if($enddt<$now){
                    $row->bidstatus='closed';
                    $close_itemids[]=$row->id;
                }
                else{
                    $sells[]=$row;
                }
            }
            
            if(!empty($close_itemids)){
                Item::whereIn('id',$close_itemids)->update([
                    'bidstatus'=>'closed'
                ]);
            }

            ResponseService::successResponse("My Auction Fetched", ['buys'=>$buys,'sells'=>$sells]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
    public function getBidHistory(Request $request) {
        $this->checkBlock();
        try {
            $user = Auth::user();
            $sql = Item::selectRaw('items.*,max(ib.bid_price) as my_bid_price,winnerib.bid_price as winner_bid_price')->with('user:id,seller_uname,name,email,mobile,profile,created_at', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area:id,name','item_payment')
            ->join('item_bids as ib','ib.item_id','=','items.id')->where('ib.user_id',$user->id)
            ->leftJoin('item_bids as winnerib','items.winnerbidid','=','winnerib.id')
            ->orderBy('ib.created_at','desc')
            ->groupBy('items.id')
            ->get();
            $bidHistories=[];
            $close_itemids=[];
            $open_itemids=[];
            foreach($sql as $row){
                if($row->my_bid_price==$row->winner_bid_price){
                    $row->iswinner=true;
                }
                else{
                    $row->iswinner=false;
                }
                $now=new \DateTime();
                $enddt=new \DateTime($row->enddt);
                if($enddt<$now && $row->bidstatus=='open'){
                    $row->bidstatus='closed';
                    $close_itemids[]=$row->id;
                }
                $bidHistories[]=$row;
            }
            if(!empty($open_itemids)){
                Item::whereIn('id',$open_itemids)->update([
                    'bidstatus'=>'open'
                ]);
            }
            if(!empty($close_itemids)){
                Item::whereIn('id',$close_itemids)->update([
                    'bidstatus'=>'closed'
                ]);
            }
            $bidHistories=Item::parseStatus($bidHistories);
            $returns=['all'=>[],'open-bid'=>[],'close-bid'=>[],'win'=>[],'completed'=>[],'complain'=>[],'lose'=>[]];
            foreach($bidHistories as $row){
                $returns['all'][]=$row;
                if($row->bidstatus=='closed' && !$row->iswinner){
                    $returns['lose'][]=$row;
                }
                elseif($row->bidstatus=='closed' && $row->iswinner && $row->user_id!=$user->id){//self win gak masuk buyer history
                    $returns['win'][]=$row;
                }
                if($row->bidstatus=='open'){
                    $returns['open-bid'][]=$row;
                }
                if($row->bidstatus=='closed' && $row->user_id!=$user->id){//self win gak masuk buyer history
                    $returns['close-bid'][]=$row;
                }
                switch($row->statusparse){
                    case 'transfer-seller':$returns['completed'][]=$row;break;
                    case 'completed':$returns['completed'][]=$row;break;
                    case 'trouble-delivery':$returns['complain'][]=$row;break;
                }
            }
            ResponseService::successResponse("Bid History Fetched", $returns);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
    public function getWaitingPayment(Request $request) {
        $this->checkBlock();
        try {
            $user = Auth::user();
            $sql = Item::select('items.*','winnerib.bid_price as winner_bid_price')->with('user:id,seller_uname,name,email,mobile,profile,created_at', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area:id,name','item_payment')
            ->join('item_bids as winnerib','items.winnerbidid','=','winnerib.id')
            ->leftJoin('item_payments as ipay','items.id','=','ipay.item_id')
            ->whereNull('ipay.id')
            ->where('winnerib.user_id',$user->id)
            // ->where('items.enddt','>=',date("Y-m-d H:i:s"))
            ->orderBy('items.created_at','desc')
            ->get();
            $returns=[];
            $waitingPayments=[];
            $close_itemids=[];
            foreach($sql as $row){
                $now=new \DateTime();
                $enddt=new \DateTime($row->enddt);
                if($enddt<$now && $row->bidstatus=='open'){
                    $row->bidstatus='closed';
                    $close_itemids[]=$row->id;
                }
                $waitingPayments[]=$row;
            }
            if(!empty($close_itemids)){
                Item::whereIn('id',$close_itemids)->update([
                    'bidstatus'=>'closed'
                ]);
            }
            
            foreach($waitingPayments as $row){
                if($row->user_id!=$user->id){//self win tidak masuk waiting payment
                    $returns[]=$row;
                }
            }
            ResponseService::successResponse("Waiting Payment Fetched", $returns);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
    public function getSellHistory(Request $request) {
        $this->checkBlock();
        try {
            $user = Auth::user();
            $sql = Item::select('items.*','winnerib.bid_price as winner_bid_price','winnerib.user_id as winner_id')->with('user:id,seller_uname,name,email,mobile,profile,created_at', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area:id,name','item_payment')
            ->leftJoin('item_bids as winnerib','items.winnerbidid','=','winnerib.id')
            ->where('items.user_id',$user->id)
            ->orderBy('items.created_at','desc')
            ->get();
            $all=[];
            $open=[];
            $closed=[];
            $now=new \DateTime();

            $close_itemids=[];
            foreach($sql as $row){
                $enddt=new \DateTime($row->enddt);
                if($enddt<$now && $row->bidstatus=='open'){
                    $row->bidstatus='closed';
                    $close_itemids[]=$row->id;
                }
            }
            if(!empty($close_itemids)){
                Item::whereIn('id',$close_itemids)->update([
                    'bidstatus'=>'closed'
                ]);
            }

            $returns=['all'=>[],'open-bid'=>[],'close-bid'=>[],'sold'=>[],'not-sold'=>[],'bid-run'=>[],'completed'=>[],'complain'=>[]];
            $sql=Item::parseStatus($sql);

            foreach($sql as $row){
                $haswinner=false;
                $hasclosed=false;
                $enddt=new \DateTime($row->enddt);
                if($row->bidstatus=='closed'){
                    if($row->winner_bid_price!=null){
                        $haswinner=true;
                    }
                }
                $row->haswinner=$haswinner;

                $returns['all'][]=$row;
                if($row->bidstatus=='closed' && (!$row->haswinner || $row->winner_id==$user->id)){//self win masuk sebagai not-sold
                    $returns['not-sold'][]=$row;
                }
                elseif($row->bidstatus=='closed' && $row->haswinner && $row->winner_id!=$user->id){
                    $returns['sold'][]=$row;
                }

                if($row->bidstatus=='open'){
                    $returns['open-bid'][]=$row;
                }
                if($row->bidstatus=='closed'){
                    $returns['close-bid'][]=$row;
                }
                switch($row->statusparse){
                    case 'transfer-seller':$returns['completed'][]=$row;break;
                    case 'completed':$returns['completed'][]=$row;break;
                    case 'trouble-delivery':$returns['complain'][]=$row;break;
                }
            }
            
            ResponseService::successResponse("Sell History Fetched", $returns);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
    public function cronCloseItems(Request $request){
        $now=date("Y-m-d H:i:s");
        $items=Item::where('enddt','<',$now)->get();
        // var_dump($items);
        foreach($items as $row){
            Item::closeItem($row->id);
            echo "close3: ".$row->id."<br>";
            
        }
    }
}
