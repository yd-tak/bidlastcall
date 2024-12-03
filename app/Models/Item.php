<?php

namespace App\Models;

use App\Models\ItemBid;
use App\Models\ItemPayment;
use App\Models\ServiceFee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Item extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'price',
        'startdt',
        'enddt',
        'bidstatus',
        'winnerbidid',
        'minbid',
        'startbid',
        'description',
        'latitude',
        'longitude',
        'address',
        'contact',
        'show_only_to_premium',
        'video_link',
        'status',
        'rejected_reason',
        'user_id',
        'image',
        'country',
        'state',
        'city',
        'area_id',
        'all_category_ids',
        'slug',
        'servicefee',
        'shippingfee',
        'closeprice',
        'totalcloseprice',
        'expire_payment_at'
    ];

    // Relationships
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function item_bid(){
        return $this->hasOne(ItemBid::class,'id','winnerbidid');
    }
    public function item_payment(){
        return $this->hasOne(ItemPayment::class);
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
    public static function closeItem($id){
        $item=Self::where('id',$id)->first();
        // echo "CLOSING : ".$item->id."<br>";
        $now=date("Y-m-d H:i:s");
        $updateData=[];
        if($item->bidstatus=='open' && $item->enddt<$now){
            $updateData['bidstatus']='closed';
            $item->bidstatus='closed';
            
        }
        if($item->bidstatus=='closed' && $item->winnerbidid!=null){
            $expire_payment_at=new \DateTime($item->enddt);
            $expire_payment_at->modify("+2 hour");
            $updateData['expire_payment_at']=$expire_payment_at->format("Y-m-d H:i:s");

            $winnerbid=ItemBid::where('id',$item->winnerbidid)->first();
            $closeprice=$winnerbid->bid_price;
            $servicefee=ServiceFee::where('minprice','<',$closeprice)->where('maxprice','>',$closeprice)->first();
            $updateData['closeprice']=$closeprice;
            $updateData['servicefee']=$servicefee->fee;
            $updateData['buyerbillprice']=$closeprice+$item->shippingfee+$servicefee->fee;
            $updateData['totalcloseprice']=$closeprice+$item->shippingfee;

            $payment=ItemPayment::where('item_id',$item->id)->first();
            if($now>$expire_payment_at->format("Y-m-d H:i:s") && $payment==null){
                $updateData['status']='not paid';
            }
            // var_dump($updateData);
            Self::where('id',$id)->update($updateData);
            $winneruser=User::where('id',$winnerbid->user_id)->first();
            $failedPayments=Self::join('item_bids as ib','items.winnerbidid','=','ib.id')->where('items.status','=','not paid')->where('ib.user_id','=',$winnerbid->user_id)->count();
            $updateUser=[
                'paymentfailure'=>$failedPayments
            ];
            $netFailure=$failedPayments-$winneruser->paymentfailuretolerance;
            if($netFailure>1){
                $updateUser['isblocked']=0;
            }
            else{
                $updateUser['isblocked']=0;
            }
            User::where('id',$winnerbid->user_id)->update($updateUser);
            
        }

    }
    public static function parseStatus($items){
        foreach($items as $row){
            $row->statusparse='';
            $row->statusparsestr='';
            if($row->bidstatus=='open'){
                $row->statusparse='open-bid';
                $row->statusparsestr='Open Bid';
            }
            elseif($row->bidstatus=='closed'){
                if($row->winnerbidid!=null){
                    if($row->item_payment==null){
                        $row->statusparse='waiting-payment';
                        $row->statusparsestr='Menunggu Pembayaran';
                    }
                    elseif($row->item_payment->status=='review'){
                        $row->statusparse='review-payment';
                        $row->statusparsestr='Review Pembayaran';
                    }
                    elseif($row->item_payment->status=='approve'){
                        if(!$row->item_payment->istransfered){
                            $row->statusparse='transfer-seller';
                            $row->statusparsestr='Transfer Seller';
                        }
                        else{
                            $row->statusparse='completed';
                            $row->statusparsestr='Selesai';
                        }
                    }
                }
                else{
                    $row->statusparse='not-sold';
                    $row->statusparsestr='Tidak Terjual';
                }
            }
        }
        return $items;
    }

    public function gallery_images() {
        return $this->hasMany(ItemImages::class);
    }

    public function custom_fields() {
        return $this->hasManyThrough(
            CustomField::class, CustomFieldCategory::class,
            'category_id', 'id', 'category_id', 'custom_field_id'
        );
    }

    public function item_custom_field_values() {
        return $this->hasMany(ItemCustomFieldValue::class);
    }

    public function featured_items() {
        return $this->hasMany(FeaturedItems::class)->onlyActive();
    }

    public function favourites() {
        return $this->hasMany(Favourite::class);
    }

    public function item_offers() {
        return $this->hasMany(ItemOffer::class);
    }

    public function user_reports() {
        return $this->hasMany(UserReports::class);
    }

    public function sliders(): MorphMany {
        return $this->morphMany(Slider::class, 'model');
    }

    public function area() {
        return $this->belongsTo(Area::class);
    }

    // Accessors
    public function getImageAttribute($image) {
        return !empty($image) ? url(Storage::url($image)) : $image;
    }

    public function getStatusAttribute($value) {
        return $this->deleted_at ? "inactive" : $value;
    }

    // Scopes
    public function scopeSearch($query, $search) {
        $search = "%" . $search . "%";
        return $query->where(function ($q) use ($search) {
            $q->orWhere('name', 'LIKE', $search)
                ->orWhere('description', 'LIKE', $search)
                ->orWhere('price', 'LIKE', $search)
                ->orWhere('image', 'LIKE', $search)
                ->orWhere('latitude', 'LIKE', $search)
                ->orWhere('longitude', 'LIKE', $search)
                ->orWhere('address', 'LIKE', $search)
                ->orWhere('contact', 'LIKE', $search)
                ->orWhere('show_only_to_premium', 'LIKE', $search)
                ->orWhere('status', 'LIKE', $search)
                ->orWhere('video_link', 'LIKE', $search)
                ->orWhere('clicks', 'LIKE', $search)
                ->orWhere('user_id', 'LIKE', $search)
                ->orWhere('country', 'LIKE', $search)
                ->orWhere('state', 'LIKE', $search)
                ->orWhere('city', 'LIKE', $search)
                ->orWhere('category_id', 'LIKE', $search)
                ->orWhereHas('category', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                })->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                });
        });
    }

    public function scopeOwner($query) {
        if (Auth::user()->hasRole('User')) {
            return $query->where('user_id', Auth::user()->id);
        }
        return $query;
    }

    public function scopeApproved($query) {
        return $query->where('status', 'approved');
    }

    public function scopeNotOwner($query) {
        return $query->where('user_id', '!=', Auth::user()->id);
    }

    public function scopeSort($query, $column, $order) {
        if ($column == "user_name") {
            return $query->leftJoin('users', 'users.id', '=', 'items.user_id')
                ->orderBy('users.name', $order)
                ->select('items.*');
        }
        return $query->orderBy($column, $order);
    }

    public function scopeFilter($query, $filterObject) {
        if (!empty($filterObject)) {
            foreach ($filterObject as $column => $value) {
                $query->where((string)$column, (string)$value);
            }
        }
        return $query;
    }

    public function scopeOnlyNonBlockedUsers($query) {
        $blocked_user_ids = BlockUser::where('user_id', Auth::user()->id)
            ->pluck('blocked_user_id');
        return $query->whereNotIn('user_id', $blocked_user_ids);
    }
}
