<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\STKRequest;
use App\Models\orders;
use App\Models\STKMpesaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use DB;

class KcbController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function generateAccessToken (){
        $consumer_key = config('kcb.consumer');
        $consumer_secret = config('kcb.secret');
        $liveURL = config('kcb.liveurl');
        $SandBoxURL = config('kcb.sandboxurl');

        $url = "https://api.buni.kcbgroup.com/token";

        $data=array(
            'grant_type'=>'client_credentials',
            'username'=>$consumer_key,
            'password'=>$consumer_secret,
        );
        $payload = http_build_query($data);
        $options=array(
             CURLOPT_URL=>$url,
             CURLOPT_POST=>true,
             CURLOPT_POSTFIELDS=>$payload,
             CURLOPT_RETURNTRANSFER=>true,
             CURLOPT_SSL_VERIFYPEER=>false,
             CURLOPT_HTTPHEADER=>array(
                'Authorization: Basic '.base64_encode($consumer_key.':'.$consumer_secret),
             ),
        );
        $Curl = curl_init();
        curl_setopt_array($Curl,$options);
        $response = curl_exec($Curl);
        // dd($response);
        if(curl_errno($Curl)){
            echo 'cURL error: '.curl_error($Curl);
        }
        curl_close($Curl);
        $access_token=json_decode($response);
        $NoSpace = str_replace(' ', '', $access_token->access_token);
        // Remove ""
        $NoQuote = str_replace('"', "", $NoSpace);
        return $NoQuote;
    }

    public function invoiceNumber(){
        $latest = orders::orderBy('created_at','DESC')->first();
        if($latest == null){
            $OrderId = 1;
        }else{
            $OrderID = $latest->id;
            $OrderId = $OrderID+1;
        }

        $InvoiceNumber = "ALTIMATE";
        return $InvoiceNumber;
    }


    public function stkRequestMakeGetRemote($phone,$cartTotal,$order_id){
        $phoneNumbers = $phone;
        $phoneNumber = $phoneNumbers;
        $amount = $cartTotal;
        $invoiceNumbers = $this->invoiceNumber();
        $invoiceNumber = "$invoiceNumbers#$order_id";
        $transactionDescription = "Payment For Invoce Number: $invoiceNumber";
        //PrepareAmount;
        $rowAmount = $amount;
        $prepareAmountString = str_replace( ',', '', $rowAmount);
        $amount = ceil($prepareAmountString);
        //PreparePhoneNumber
        $rowPhoneNumber = $phoneNumbers;
        $preparePhoneNumberString = str_replace( '+', '', $rowPhoneNumber);
        $mobile = $preparePhoneNumberString;

       //  Invoioice
        $postData = array(
           "phoneNumber"=> $mobile,
           "amount"=> $amount,
           "invoiceNumber"=> $invoiceNumber,
           "sharedShortCode"=> true,
           "orgShortCode"=> "",
           "orgPassKey"=> "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919",
           "callbackUrl"=> "https://api.altimate.co.ke/stk-callback",
           "transactionDescription"=> "Purchase Invoice #".$invoiceNumber
        );
        $prepare = json_encode($postData);

        $curl = curl_init();
        curl_setopt_array($curl, array(
           CURLOPT_URL => 'https://api.buni.kcbgroup.com/mm/api/request/1.0.0/stkpush',
           CURLOPT_RETURNTRANSFER => true,
           CURLOPT_ENCODING => '',
           CURLOPT_MAXREDIRS => 10,
           CURLOPT_TIMEOUT => 0,
           CURLOPT_FOLLOWLOCATION => true,
           CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
           CURLOPT_CUSTOMREQUEST => 'POST',
           CURLOPT_POSTFIELDS =>$prepare,
           CURLOPT_HTTPHEADER => array(
               'accept: application/json',
               'operation: STKPush',
               'Content-Type: application/json',
               'Authorization: Bearer '.$this->generateAccessToken(),
           ),
        ));

       $curl_response = curl_exec($curl);

       $curl_content=json_decode($curl_response);
       curl_close($curl);
       Log::info($curl_response);
       $MerchantRequestID = $curl_content->response->MerchantRequestID;
       $CheckoutRequestID = $curl_content->response->CheckoutRequestID;
       $table = 'lnmo_api_response';
       $user_id = 1;


        // Insert MerchantRequestID
       $curl_content=json_decode($curl_response);
       $MerchantRequestID = $MerchantRequestID;
       $mpesa_transaction = new STKRequest;
       $mpesa_transaction->CheckoutRequestID =  $CheckoutRequestID;
       $mpesa_transaction->MerchantRequestID =  $MerchantRequestID;
       $mpesa_transaction->user_id =  $user_id;
       $mpesa_transaction->PhoneNumber =  $mobile;
       $mpesa_transaction->Amount =  $amount;
       $mpesa_transaction->save();

       $STKMpesaTransaction = new STKMpesaTransaction;
       $STKMpesaTransaction->user_id = $user_id;
       $STKMpesaTransaction->CheckoutRequestID = $CheckoutRequestID;
       $STKMpesaTransaction->MerchantRequestID = $MerchantRequestID;
       $STKMpesaTransaction->save();

        return $this->checklast($MerchantRequestID,$table,$curl_response,$user_id,$invoiceNumber);
   }

    public function stkRequestMakeGet(){
        $phoneNumbers = "254723014032";
        $phoneNumber = $phoneNumbers;
        $amount = "1";
        $invoiceNumber = $this->invoiceNumber();
        $transactionDescription = "Payment For Invoce Number: $invoiceNumber";
        //PrepareAmount;
        $rowAmount = $amount;
        $prepareAmountString = str_replace( ',', '', $rowAmount);
        $amount = ceil($prepareAmountString);
        //PreparePhoneNumber
        $rowPhoneNumber = $phoneNumbers;
        $preparePhoneNumberString = str_replace( '+', '', $rowPhoneNumber);
        $mobile = $preparePhoneNumberString;

       //  Invoioice
        $postData = array(
           "phoneNumber"=> $mobile,
           "amount"=> $amount,
           "invoiceNumber"=> "5993428#".$invoiceNumber,
           "sharedShortCode"=> true,
           "orgShortCode"=> "",
           "orgPassKey"=> "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919",
           "callbackUrl"=> "https://api.altimate.co.ke/stk-callback",
           "transactionDescription"=> "Purchase Invoice #".$invoiceNumber
        );
        $prepare = json_encode($postData);

        $curl = curl_init();
        curl_setopt_array($curl, array(
           CURLOPT_URL => 'https://api.buni.kcbgroup.com/mm/api/request/1.0.0/stkpush',
           CURLOPT_RETURNTRANSFER => true,
           CURLOPT_ENCODING => '',
           CURLOPT_MAXREDIRS => 10,
           CURLOPT_TIMEOUT => 0,
           CURLOPT_FOLLOWLOCATION => true,
           CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
           CURLOPT_CUSTOMREQUEST => 'POST',
           CURLOPT_POSTFIELDS =>$prepare,
           CURLOPT_HTTPHEADER => array(
               'accept: application/json',
               'operation: STKPush',
               'Content-Type: application/json',
               'Authorization: Bearer '.$this->generateAccessToken(),
           ),
        ));

       $curl_response = curl_exec($curl);

       $curl_content=json_decode($curl_response);
       curl_close($curl);
       Log::info($curl_response);
       $MerchantRequestID = $curl_content->response->MerchantRequestID;
       $CheckoutRequestID = $curl_content->response->CheckoutRequestID;
       $table = 'lnmo_api_response';
       $user_id = 1;


        // Insert MerchantRequestID
       $curl_content=json_decode($curl_response);
       $MerchantRequestID = $MerchantRequestID;
       $mpesa_transaction = new STKRequest;
       $mpesa_transaction->CheckoutRequestID =  $CheckoutRequestID;
       $mpesa_transaction->MerchantRequestID =  $MerchantRequestID;
       $mpesa_transaction->user_id =  $user_id;
       $mpesa_transaction->PhoneNumber =  $mobile;
       $mpesa_transaction->Amount =  $amount;
       $mpesa_transaction->save();

       $STKMpesaTransaction = new STKMpesaTransaction;
       $STKMpesaTransaction->user_id = $user_id;
       $STKMpesaTransaction->CheckoutRequestID = $CheckoutRequestID;
       $STKMpesaTransaction->MerchantRequestID = $MerchantRequestID;
       $STKMpesaTransaction->save();

        return $this->checklast($MerchantRequestID,$table,$curl_response,$user_id);
   }

    // Expects invoicenumber,amount & phone number
    public function stkRequestMake(Request $request){
         $phoneNumber = $request->phoneNumber;
         $amount = $request->amount;
         $invoiceNumber = $this->invoiceNumber();
         $transactionDescription = "Payment For Invoce Number: $invoiceNumber";
         //PrepareAmount;
         $rowAmount = $request->amount;
         $prepareAmountString = str_replace( ',', '', $rowAmount);
         $amount = ceil($prepareAmountString);
         //PreparePhoneNumber
         $rowPhoneNumber = $request->phoneNumber;
         $preparePhoneNumberString = str_replace( '+', '', $rowPhoneNumber);
         $mobile = $preparePhoneNumberString;

        //  Invoioice
         $postData = array(
            "phoneNumber"=> $mobile,
            "amount"=> $amount,
            "invoiceNumber"=> "5993428#".$invoiceNumber,
            "sharedShortCode"=> true,
            "orgShortCode"=> "",
            "orgPassKey"=> "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919",
            "callbackUrl"=> "https://api.altimate.co.ke/stk-callback",
            "transactionDescription"=> "Purchase Invoice #".$invoiceNumber
         );
         $prepare = json_encode($postData);

         $curl = curl_init();
         curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.buni.kcbgroup.com/mm/api/request/1.0.0/stkpush',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$prepare,
            CURLOPT_HTTPHEADER => array(
                'accept: application/json',
                'operation: STKPush',
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->generateAccessToken(),
            ),
         ));

        $curl_response = curl_exec($curl);

        $curl_content=json_decode($curl_response);
        curl_close($curl);
        Log::info($curl_response);
        $MerchantRequestID = $curl_content->response->MerchantRequestID;
        $CheckoutRequestID = $curl_content->response->CheckoutRequestID;
        $table = 'lnmo_api_response';
        $user_id = Auth::User()->id;


         // Insert MerchantRequestID
        $curl_content=json_decode($curl_response);
        $MerchantRequestID = $MerchantRequestID;
        $mpesa_transaction = new STKRequest;
        $mpesa_transaction->CheckoutRequestID =  $CheckoutRequestID;
        $mpesa_transaction->MerchantRequestID =  $MerchantRequestID;
        $mpesa_transaction->user_id =  $user_id;
        $mpesa_transaction->PhoneNumber =  $mobile;
        $mpesa_transaction->Amount =  $amount;
        $mpesa_transaction->save();

        $STKMpesaTransaction = new STKMpesaTransaction;
        $STKMpesaTransaction->user_id = $user_id;
        $STKMpesaTransaction->CheckoutRequestID = $CheckoutRequestID;
        $STKMpesaTransaction->MerchantRequestID = $MerchantRequestID;
        $STKMpesaTransaction->save();

         return $this->checklast($MerchantRequestID,$table,$curl_response,$user_id);
    }


    public function stkCallback(Request $request){
        Log::info($request->getContent());
        $content=json_decode($request->getContent(), true);
        $CheckoutRequestID = $content['Body']['stkCallback']['CheckoutRequestID'];
        $MerchantRequestID = $content['Body']['stkCallback']['MerchantRequestID'];

        $nameArr = [];
        foreach ($content['Body']['stkCallback']['CallbackMetadata']['Item'] as $row) {

            if(empty($row['Value'])){
                continue;
            }
            $nameArr[$row['Name']] = $row['Value'];
        }

        DB::table('lnmo_api_response')->where('MerchantRequestID',$MerchantRequestID)->update($nameArr);
        $updateStatus = array(
            'status' =>1
        );
        DB::table('lnmo_api_response')->where('MerchantRequestID',$MerchantRequestID)->update($updateStatus);
        return response()->json(['message' => 'CallBack Received successfully!']);
        // Return to main website clear cart & redirect to thank you page
    }

    public function checklast($AccID,$table,$curl_response,$user,$invoiceNumber){
        $TableData = DB::table('lnmo_api_response')->where('MerchantRequestID', $AccID)->where('status','1')->get();
        if($TableData->isEmpty()){
            sleep(10);
            return $this->checklast($AccID,$table,$curl_response,$user,$invoiceNumber);
        }else{
            //Go To Requestes and set status to 1, Best alternative is js sockets, We have a temporary fix
            $UpdateDetails = array(
                'status'=>1,
            );
            $UpdateDetail = array(
                'user_id'=>$user,
            );
            // Update Payments Table
            DB::table('s_t_k_requests')->where('CheckoutRequestID',$AccID)->update($UpdateDetails);
            DB::table('lnmo_api_response')->where('CheckoutRequestID',$AccID)->update($UpdateDetail);
            foreach($TableData as $table){
                $message = "We have received your payment of $table->Amount for Invoice:$invoiceNumber on $table->updateTime. Thank you for choosing Altimate Business Machines!";
                $this->sendSMS($table->PhoneNumber,$message);
            }

            // return $curl_response;
            return redirect()->away('https://www.altimate.co.ke/checkout/order-received/');

        }
    }

    public function sendSMS($phone,$message){
        $beginDate = date('Y-m-d');
        $beginTime = date('h:i:s');


        $key = config('kcb.smsToken');
        $senderid = "Altimate";
        //
        $url = 'https://restapi.uwaziimobile.com/v1/send';

        $post_data=array(
            'number'=>$phone,
            'senderID'=>$senderid,
            'text'=>$message,
            'type'=>'sms',
            'beginDate'=>$beginDate,
            'beginTime'=>$beginTime,
            'lifetime'=>'86400',
            'delivery'=>false,
        );
        // dd($post_data);
        $post_data = [
            [
                "number" => [$phone],
                "senderID" => $senderid,
                "text" => $message,
                "type" => "sms",
                "beginDate" => $beginDate,
                "beginTime" => $beginTime,
                "lifetime" => 86400,
                "delivery" => false
            ],
        ];

        $data_string = json_encode($post_data);
        // dd($data_string);
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Accept: application/json',
                'charset: UTF-8',
                'X-Access-Token: '.$key,
                )
            );

        $response = curl_exec($ch);
        dd($response);
        curl_close($ch);
    }

    // For Wordress here add add_action then use the below method to process send_order

    // add_action('woocommerce_thankyou', 'send_order');

    function send_order($order_id){
        $order = wc_get_order( $order_id );
        $user = $order->get_user();
        $billing_phone = $order->get_billing_phone();
        $payment_method = $order->get_payment_method();
        $phoneNumber = $user->billing_phone;
        $cartTotal = $order->get_total();
        if($payment_method == "cod"){
            // echo "<script>alert('Cash On Delivery')</script>";
        }else{
            /*** Your code ***/
            echo "<script>window.location = 'https://api.altimate.co.ke/stkrequest-get/$billing_phone/$cartTotal/$order_id'</script>";
        }

    }
}
