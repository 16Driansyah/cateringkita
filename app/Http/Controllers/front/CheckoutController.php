<?php

namespace App\Http\Controllers\front;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Session;
use App\Order;
use App\OrderDetails;
use App\Payment;
use App\User;
use App\About;
use App\Addons;
use App\Cart;
use App\Transaction;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;

class CheckoutController extends Controller
{

    /**
     * payment view
     */
    public function index()
    {
        return view('stripe-payment');
    }
    
    public function charge(Request $request)
    {
        $getdata=User::select('token','firebase')->where('type','1')->first();
        try {

            $getuserdata=User::where('id',Session::get('id'))
            ->get()->first();

            $delivery_charge = $request->delivery_charge;
            $address = $request->address;
            $lat = $request->lat;
            $lang = $request->long;
            $order_total = $request->order_total;
            $building = $request->building;
            $landmark = $request->landmark;
            $city = @$request->city;
            $state = @$request->state;
            $country = @$request->country;
            $pincode = $request->postal_code;

            if ($request->discount_amount == "NaN") {
                $discount_amount = "0.00";
            } else {
                $discount_amount = $request->discount_amount;
            }

            $getpaymentdata=Payment::select('test_secret_key','live_secret_key','environment')->where('payment_name','Stripe')->first();

            if ($getpaymentdata->environment=='1') {
                $stripe_secret = $getpaymentdata->test_secret_key;
            } else {
                $stripe_secret = $getpaymentdata->live_secret_key;
            }

            Stripe::setApiKey($stripe_secret);

            $customer = Customer::create(array(
                'email' => $request->stripeEmail,
                'source' => $request->stripeToken,
                'name' => $getuserdata->name
            ));

            $charge = Charge::create(array(
                'customer' => $customer->id,
                'amount' => $order_total*100,
                'currency' => 'usd',
                'description' => 'Food Service',
            ));

            $order_number = substr(str_shuffle(str_repeat("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 10)), 0, 10);


            $order = new Order;
            $order->order_number =$order_number;
            $order->branch_id =$_COOKIE['branch'];
            $order->user_id =Session::get('id');
            $order->order_total =$order_total;
            $order->razorpay_payment_id =$charge['id'];
            $order->payment_type ='2';
            $order->order_type =$request->order_type;
            $order->status ='1';
            $order->address =$address;
            $order->building =$building;
            $order->landmark =$landmark;
            $order->pincode =$pincode;
            $order->lat =$lat;
            $order->lang =$lang;
            $order->promocode =$request->promocode;
            $order->discount_amount =$discount_amount;
            $order->discount_pr =$request->discount_pr;
            $order->tax =$request->tax;
            $order->tax_amount =$request->tax_amount;
            $order->delivery_charge =$delivery_charge;
            $order->order_notes =$request->notes;
            $order->order_from ='web';
            $order->save();

            $order_id = DB::getPdo()->lastInsertId();
            $data=Cart::where('cart.user_id',Session::get('id'))
            ->get();

            foreach ($data as $value) {
                $OrderPro = new OrderDetails;
                $OrderPro->order_id = $order_id;
                $OrderPro->branch_id = $_COOKIE['branch'];
                $OrderPro->user_id = $value['user_id'];
                $OrderPro->item_id = $value['item_id'];
                $OrderPro->item_name = $value['item_name'];
                $OrderPro->item_image = $value['item_image'];
                $OrderPro->addons_price = $value['addons_price'];
                $OrderPro->addons_name = $value['addons_name'];
                $OrderPro->price = $value['price'];
                $OrderPro->variation_id = $value['variation_id'];
                $OrderPro->variation_price = $value['variation_price'];
                $OrderPro->variation = $value['variation'];
                $OrderPro->qty = $value['qty'];
                $OrderPro->item_notes = $value['item_notes'];
                $OrderPro->addons_id = $value['addons_id'];
                $OrderPro->save();
            }
            $cart=Cart::where('user_id', Session::get('id'))->delete();
            $count=Cart::where('user_id',Session::get('id'))->count();

            try{
                $getlogo = About::select('logo')->where('id','=','1')->first();
                $getusers = Order::with('users')->where('order.id', $order_id)->get()->first();
                $getorders=OrderDetails::with('itemimage')->select('order_details.id','order_details.qty','order_details.price as total_price','item.id','item.item_name','order_details.item_id','order_details.addons_id','order_details.item_notes','order_details.variation_price')
                ->join('item','order_details.item_id','=','item.id')
                ->join('order','order_details.order_id','=','order.id')
                ->where('order_details.order_id',$order_id)->get()->toArray();

                $arrayName = array();
                foreach ($getorders as $key => $value) {
                   $arr = explode(',', $value['addons_id']);
                   $arrayName[$key]=$value;
                   $arrayName[$key]['addons']=Addons::whereIn('id',$arr)->get()->toArray();
                }; 
                
                $email=$getuserdata->email;
                $name=$getuserdata->name;
                $logo=$getlogo->logo;
                $currency=$getdata->currency;

                $data=['getusers'=>$getusers,'getorders'=>$arrayName,'email'=>$email,'name'=>$name,'logo'=>$logo,'currency'=>$currency];
                Mail::send('Email.emailinvoice',$data,function($message)use($data){
                    $message->from(env('MAIL_USERNAME'))->subject('Order');
                    $message->to($data['email']);
                } );
            }catch(\Swift_TransportException $e){
                $response = $e->getMessage() ;
                return response()->json(['status'=>0,'message'=>trans('messages.email_error')],200);
            }
            
            Session::put('cart', $count);

            session()->forget(['offer_amount','offer_code']);

            
            try{
                $admintitle = "Order";
                $adminbody = 'You have received a new order '.$order_number.'';
                $admingoogle_api_key = $getdata->firebase; 
                
                $adminregistrationIds = $getdata->token;
                #prep the bundle
                $adminmsg = array
                    (
                    'body'  => $adminbody,
                    'title' => $admintitle,
                    'sound' => 1/*Default sound*/
                    );
                $adminfields = array
                    (
                    'to'            => $adminregistrationIds,
                    'notification'  => $adminmsg
                    );
                $adminheaders = array
                    (
                    'Authorization: key=' . $admingoogle_api_key,
                    'Content-Type: application/json'
                    );
                #Send Reponse To FireBase Server
                $adminch = curl_init();
                curl_setopt( $adminch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
                curl_setopt( $adminch,CURLOPT_POST, true );
                curl_setopt( $adminch,CURLOPT_HTTPHEADER, $adminheaders );
                curl_setopt( $adminch,CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $adminch,CURLOPT_SSL_VERIFYPEER, false );
                curl_setopt( $adminch,CURLOPT_POSTFIELDS, json_encode( $adminfields ) );

                $result = curl_exec ( $adminch );
                curl_close ( $adminch );
            }catch(\Swift_TransportException $e){
                $response = $e->getMessage() ;
                return response()->json(['status'=>0,'message'=>trans('messages.notification_error')],200);
            }

            return response()->json(['status'=>1,'message'=>trans('messages.order_placed')],200); 

            // return 'Charge successful, you get the course!';
        } catch (\Exception $ex) {
            $response = $ex->getMessage() ;
            return response()->json(['status'=>0,'message'=>trans('messages.wrong')],200);
        }
    }

    public function addmoneystripe(Request $request)
    {
        
        $getuserdata=User::where('id',Session::get('id'))
        ->get()->first();

        $getpaymentdata=Payment::select('test_secret_key','live_secret_key','environment')->where('payment_name','Stripe')->first();

        if ($getpaymentdata->environment=='1') {
            $stripe_secret = $getpaymentdata->test_secret_key;
        } else {
            $stripe_secret = $getpaymentdata->live_secret_key;
        }

        Stripe::setApiKey($stripe_secret);

        $customer = Customer::create(array(
            'email' => Session::get('email'),
            'source' => $request->stripeToken,
            'name' => $getuserdata->name,
        ));

        $charge = Charge::create(array(
            'customer' => $customer->id,
            'amount' => $request->add_money*100,
            'currency' => 'usd',
            'description' => 'Add Money to wallet',
        ));

        $wallet = $getuserdata->wallet + $request->add_money;

        $UpdateWalletDetails = User::where('id', Session::get('id'))
        ->update(['wallet' => $wallet]);

        if ($UpdateWalletDetails) {
            $order_number = substr(str_shuffle(str_repeat("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 10)), 0, 10);

            $Wallet = new Transaction;
            $Wallet->user_id = Session::get('id');
            $Wallet->order_id = NULL;
            $Wallet->order_number = $order_number;
            $Wallet->wallet = $request->add_money;
            $Wallet->payment_id = $charge['id'];
            $Wallet->order_type = 4;
            $Wallet->transaction_type = '4';
            $Wallet->save();

            return response()->json(['status'=>1,'message'=>trans('messages.money_added')],200); 
        } else {
            return response()->json(['status'=>0,'message'=>trans('messages.money_error')],200);
        }
    }
    
}