<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Gloudemans\Shoppingcart\Facades\Cart; //introduces the cart lib
use App\Models\Product;
use App\Models\SMS;

class orders extends Model
{
    use HasFactory;
    protected $fillable=['total', 'status'];
    public function orderFields(){

        return $this->belongsToMany(Product::class)->withPivot('qty', 'total');

    }

    public static function createOrder(){

        $user = Auth::user();
        $order = $user->orders()->create(['total'=>Cart::total(),'status'=>'pending']);

        $cartItems = Cart::content();
        foreach($cartItems as $cartItem)

            $order->orderFields()->attach($cartItem->id,['qty'=>$cartItem->qty, 'tax' =>Cart::tax(), 'total'=>Cart::total()]);

            //Insert Notification
            // $Notifications = new Notifications;
            // $Notifications->type = 'Order';
            // $Notifications->content = 'You have a new Order';
            // $Notifications->save();

            // Notify Supplier
       }

    //    SMS::sendMessage();
}
