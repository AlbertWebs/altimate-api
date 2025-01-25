<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\STKRequest;
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

        $url = 'https://api.buni.kcbgroup.com/token';

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
            "invoiceNumber"=> "7845649#".$invoiceNumber,
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

    public function checklast($AccID,$table,$curl_response,$user){
        $TableData = DB::table('lnmo_api_response')->where('MerchantRequestID', $AccID)->where('status','1')->get();
        if($TableData->isEmpty()){
            sleep(10);
            return $this->checklast($AccID,$table,$curl_response,$user);
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
            return $curl_response;
        }
    }
}
