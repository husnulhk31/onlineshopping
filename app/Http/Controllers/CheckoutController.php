<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Exception;

use Midtrans\Snap;
use Midtrans\Config;

class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        //Save users data
        $user = Auth::user();
        #$user->update($request->except('total_price'));

        // Proses Checkout
        $code = 'STORE-' . mt_rand(000000,999999); 
        $carts = Cart::with(['product','user'])
            ->where('users_id', Auth::user()->id)
            ->get();

        //transaction create
        $transaction = Transaction::create([
            'users_id'=> Auth::user()->id,
            'insurance_price' => 0,
            'shipping_price' => 0,
            'total_price'=>$request->total_price,
            'transaction_status'=>'PENDING',
            'code'=>$code,
        ]);

        //transaction_detail
        foreach ($carts as $cart) {
            $trx = 'TRX-'.mt_rand(000000,999999); 

            TransactionDetail::create([
                'transactions_id'=>$transaction->id,
                'products_id' => $cart->product->id,
                'price' => $cart->product->price,
                'shipping_status'=>'PENDING',
                'resi'=>'',
                'code'=>$trx,
            ]);
        }

        //Delete Cart Data
        Cart::where('users_id', Auth::user()->id)->delete();

        //Konfigurasi Midtarns
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        $midtrans = [
            'transaction_details' => [
                'order_id' =>$code,
                'gross_amount' => (int) $request->total_price,

            ],
            'customer_details' => [
                'first_name' => Auth::user()->name,
                'email' =>Auth::user()->email,
            ],
            'enabled_payments' => [
                'gopay','permata_va','bank_transfer'
            ],
            'vtweb' => []

        ];

        try {

            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;

            return redirect($paymentUrl);
        }
            catch(Exception $e) {
            echo $e->getMessage();

        }
    }

    public function callback(Request $request)
    {
        
    }
}
