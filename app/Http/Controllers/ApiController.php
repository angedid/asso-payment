<?php

namespace App\Http\Controllers;

use App\Domain\Model\AccessToken;
use App\Domain\Model\Holder;
use App\Domain\Model\MobileAccount;
use App\Domain\Model\Payment;
use App\Domain\Model\PaymentServiceProvider;
use App\Domain\Model\Tenant;
use App\Jobs\ProcessMessages;
use App\Models\Country;
use App\Models\Currency;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Crypto\Rsa\KeyPair;
use Spatie\Crypto\Rsa\PrivateKey;

class ApiController extends Controller
{

    public function provisionTenant(Request $request){
        //return response(GlobalResultHandler::buildSuccesResponseArray($request->getContent()), 200);

        /*$userToRegisterJsonString = file_get_contents('php://input');
        $userToRegisterArray =  json_decode($userToRegisterJsonString, true);*/
        $userToRegisterArray = json_decode($request->getContent(), true);


        $tenant =  $userToRegisterArray['data']['tenant'];
        $adminuser =  $userToRegisterArray['data']['user'];
        //$role_tenant_admin = $userToRegisterArray['data']['role_tenant_admin'];
        //$role_tenant_user = $userToRegisterArray['data']['role_tenant_user'];

        DB::beginTransaction();

        try{
            $tenantData = [
                'id' =>$tenant['id'],
                'name' => $tenant['name'],
                'city' => $tenant['city'],
                'location' => $tenant['location'],
                'region' => $tenant['region'],
                'country' => $tenant['country'],
                'phone_number' => $tenant['phone_number'],
                'website' => $tenant['website'],
                'address' => $tenant['address'],
                'active' => $tenant['active'],
                'owner' => $adminuser['id'],
                'taxpayer_number' => $tenant['taxpayer_number'],
                'number_trade_register' => $tenant['number_trade_register'],
                'network' => $tenant['network'],
            ];

            $tenant = Tenant::create($tenantData);

            $holderData = [
                'id' => $adminuser['id'],
                'tenant_id' => $adminuser['tenant_id'],
                'type' => $adminuser['type'],
                'full_name' => $adminuser['full_name'],
                'username' => $adminuser['username'],
                'email' => $adminuser['email'],
                'phone_number' => $adminuser['phone_number'],
                'location' => $tenant['location'],
                'region' => $adminuser['region'],
                'country' => $adminuser['country'],
                'active' => $adminuser['active'],
                'network' => $adminuser['network'],
            ];
            //return response(GlobalResultHandler::buildSuccesResponseArray($userData), 200);
            Holder::create($holderData);

            /*Role::create([
                'id' => $role_tenant_admin['id'],
                'tenant_id' => $role_tenant_admin['tenant_id'],
                'name' => $role_tenant_admin['name'],
                'description' => $role_tenant_admin['description'],
                'groups_playing_role' => $role_tenant_admin['groups_playing_role'],
                'users_playing_role' => $role_tenant_admin['users_playing_role'],
                'scopes' => $role_tenant_admin['scopes'],
                'active' => $role_tenant_admin['active'],
            ]);

            Role::create([
                'id' => $role_tenant_user['id'],
                'tenant_id' => $role_tenant_user['tenant_id'],
                'name' => $role_tenant_user['name'],
                'description' => $role_tenant_user['description'],
                'groups_playing_role' => $role_tenant_user['groups_playing_role'],
                'users_playing_role' => $role_tenant_user['users_playing_role'],
                'scopes' => $role_tenant_user['scopes'],
                'active' => $role_tenant_user['active'],
            ]);

            $tenant->owner = $user->id;
            $tenant->save();

            //$userToSave->save();
            //$tenantToSave->save();*/

        }catch (\Exception $e){

            DB::rollBack();

            return response(GlobalResultHandler::buildFaillureReasonArray('Faild to save Tenant Provision : ' .$e->getMessage()), 200);

        }
        DB::commit();

        return response(GlobalResultHandler::buildSuccesResponseArray('Tenant Provision saved successfully'), 200);

    }

    public function saveAccessToken(Request $request){
        Log::info($request->getContent());
        $accessTokenArray = json_decode(json_decode($request->getContent(), true), true);
        $token_type = $accessTokenArray['token_type'];
        $expires_in = $accessTokenArray['expires_in'];
        $access_token = $accessTokenArray['access_token'];
        //$refresh_token = $accessTokenArray['refresh_token'];

        $tokendata = explode('.', $access_token);
        //Log::info("Tokendata: " . base64_decode($tokendata[1]));
        $jsonAccessTokendata = json_decode(base64_decode($tokendata[1]), true);
        $user_id = $jsonAccessTokendata['sub'];
        $client_id = $jsonAccessTokendata['aud'];
        $jti = $jsonAccessTokendata['jti'];
        $scopes = json_encode($jsonAccessTokendata['scopes']);
        $expires_at = $jsonAccessTokendata['exp'];
        AccessToken::create([
            'id' => $jti,
            'user_id' => $user_id,
            'client_id' => $client_id,
            'name' => '',
            'scopes' => $scopes,
            'revoked' => false,
            'token_type' => $token_type,
            'expires_in' => $expires_in,
            'expires_at' => new DateTime("@$expires_at")
        ]);

        return response(GlobalResultHandler::buildSuccesResponseArray('Access Token saved successfully'), 200);
    }

    public function logout(Request $request){
        $jtiArray = json_decode($request->getContent(), true);
        $jti = $jtiArray['jti'];
        $accessToken = AccessToken::where('id', $jti)->first();
        if($accessToken === null){
            return response(GlobalResultHandler::buildFaillureReasonArray('Token with jti: ' .$jti.' not found'), 200);
        }
        $accessToken->revoked = true;
        $accessToken->save();

        return response(GlobalResultHandler::buildSuccesResponseArray('Access Token revoked successfully'), 200);
    }

    public function makeServicePayment(Request $request)
    {
        //$requestBodyString = file_get_contents('php://input');
        $requestBodyString = $request->getContent(); //file_get_contents('php://input');

        $paymentData =  json_decode($requestBodyString, true);

        $validator = Validator::make(
            $paymentData,
            [
                'user_id' => 'required|string|exists:holders,id',
                'payment_service_provider_id' => 'required|string|exists:payment_service_providers,id',
                'tenant_id' => 'required|string|min:1|exists:tenants,id',
                'amount' => 'required|numeric',
                'currency_id' => 'required|string|exists:currencies,id',
                'order_id' => 'required|uuid',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $paymentServiceProvider = PaymentServiceProvider::where('id', '=', $paymentData['payment_service_provider_id'])->first();
        if ($paymentServiceProvider === null) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Payment Service Provider choosen not found'), 200);
        }

        $apiUrl = $paymentServiceProvider->api_url;

        $currency = Currency::where('id', '=', $paymentData['currency_id'])->first();
        if ($currency === null) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Currency choosen not found'), 200);
        }

        if ($paymentServiceProvider->payment_method === PaymentServiceProvider::MOBILEMONEY){
            $pays = Country::all();
            $paese = [];
            foreach ($pays as $pay){
                $paese[] = $pay->code;
            }
            $countries = join (',',$paese);
            $validator = Validator::make(
                $paymentData,
                [
                    'phone_number'=>'phone:'.$countries,
                ]
            );
            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }

            if ($paymentServiceProvider->name === 'MTN CAMEROON'){

                $nkallaMtnAccountData = json_decode(env('MOMO_PAYMENT_ACCOUNT_DATA'), true);

                try {
                    $accessTokenResponse = Http::withHeaders([
                        'Authorization' => 'Basic '.base64_encode($nkallaMtnAccountData['X-Reference-Id'].':'.$nkallaMtnAccountData['apiKey']),
                        'Ocp-Apim-Subscription-Key' => $nkallaMtnAccountData['registration_key']['primary']
                    ])->post($nkallaMtnAccountData['token_endpoint'], []);

                    $accessToken = json_decode($accessTokenResponse->body(),true)['access_token'];

                    Log::info("MTN TOKEN: " . $accessToken);

                    $paymentid = Str::uuid()->toString();
                    $donneesDepayement = json_encode(
                        [
                            'amount'=>doubleval($paymentData['amount']),
                            'currency'=>'EUR',
                            'externalId'=>$paymentid,
                            'payer'=>[
                                'partyIdType'=>'MSISDN',
                                'partyId'=>$paymentData['phone_number']
                            ],
                            'payerMessage'=>$paymentData['order_id'],
                            'payeeNote'=>'ASSO License Payment'
                        ]);

                    $account = [
                        'accountnumber'=>$paymentData['phone_number'],
                        'holdername'=>'',
                        'expirationdate'=>'',
                        'issuer'=>$paymentServiceProvider->id
                    ];
                    $start_date = Carbon::now();
                    //$date = $start_date->format('Y-m-d H:i:s');

                    $antPayment = Payment::where('order_number', $paymentData['order_id'])->where('status', Payment::SUCCESS)->get();
                    if(count($antPayment) > 0){
                        return response(array('success' => 0, 'faillure' => 1, 'raison' => "Order Yey Paid"), 200);
                    }
                    $payment =  Payment::create(
                        [
                            'id'=>$paymentid,
                            'tenant_id'=>$paymentData['tenant_id'],
                            'holder_id'=>$paymentData['user_id'],
                            'payment_service_provider_id'=>$paymentData['payment_service_provider_id'],
                            'account_used'=>json_encode($account),
                            'amount'=>doubleval($paymentData['amount']),
                            'currency_id'=>$paymentData['currency_id'],
                            'order_number'=>$paymentData['order_id'],
                            'start_date'=>$start_date,
                            'end_date'=>null,
                            'status'=> Payment::PENDING,
                            'details'=> 'Payment initiated'
                        ]);

                    $mobileAccount = MobileAccount::where('tenant_id', $paymentData['tenant_id'])
                        ->where('phone_number', $paymentData['phone_number'])->first();

                    if ($mobileAccount === null) {
                        MobileAccount::create([
                            'id' => Str::uuid()->toString(),
                            'tenant_id' => $paymentData['tenant_id'],
                            'holder_id' => $paymentData['user_id'],
                            'phone_number'=>$paymentData['phone_number'],
                            'holder_name'=> '',
                            'issuer' => $paymentServiceProvider->id,
                            'active' => true
                        ]);
                    }




                        /*$paymentid, $paymentData['tenantid'], $paymentData['userid'], $paymentData['paymentserviceproviderid'],
                        json_encode($account), $paymentData['amount'], json_encode($currency), $paymentData['orderid'], $start_date, null, Payment::PENDING, '');
                    $payment->save();*/

                    ProcessMessages::dispatch(
                        env('LICENSE_PAYMENT_INITIATED_EXCHANGE'),
                        env('RABBIT_MQ_EXCHANGE_TYPE', 'fanout'),
                        json_encode($payment)
                    );

                    /*$output=null;
                    $retval=null;
                    //$lofomboMtnAccountData = json_decode(env('MOMO_PAYMENT_ACCOUNT_DATA'), true);
                    $command = 'curl -v -X POST -H "Authorization: '. 'Bearer '.$accessToken.'"  -H "X-Reference-Id: '.  $paymentid .'"  -H "X-Target-Environment: '.$lofomboMtnAccountData['targetEnvironment'].'" '. '  -H "Content-Type: application/json" '. ' -H "Ocp-Apim-Subscription-Key: '.$lofomboMtnAccountData['registration_key']['primary'].'" --data-ascii \''.$donneesDepayement.'\' '. $lofomboMtnAccountData['resource_endpoints']['base_url'].$lofomboMtnAccountData['resource_endpoints']['payment_path'];
                    //echo $command. '<br>';
                    exec($command, $output, $retval);
                    echo "Returned with status $retval and output:\n";
                    print_r($output);
                    return $command;*/

                    /*$client = new client();

                    $response = $client->post($urlSection['HOST_NOTIFICATION'].'/api/emails', [
                        'headers' => [
                            'Authorization' => 'Bearer '.$accessToken,
                            'Ocp-Apim-Subscription-Key' => $lofomboMtnAccountData['registration_key']['primary'],
                            'X-Target-Environment'=> $lofomboMtnAccountData['targetEnvironment'],
                            'X-Reference-Id'=> $payment->paymentid,
                            'Content-Type'=> 'application/json',
                        ],
                        'body' => $donneesDepayement
                    ]);*/
                    $header = [
                        'Authorization'                   => 'Bearer '.$accessToken,
                        'Ocp-Apim-Subscription-Key'       => $nkallaMtnAccountData['registration_key']['primary'],
                        'X-Target-Environment'            => $nkallaMtnAccountData['targetEnvironment'],
                        'X-Reference-Id'=> $payment->id,
                        'Content-Type'=> 'application/json',
                    ];
                    $requestToPayUrl = $nkallaMtnAccountData['resource_endpoints']['base_url'].$nkallaMtnAccountData['resource_endpoints']['payment_path'];
                    Log::info("Request to Pay url: " . $requestToPayUrl);
                    Log::info("header: " . json_encode($header));
                    Log::info("body: " . json_encode($donneesDepayement));
                    $paymentResponse = Http::withHeaders($header)->withBody($donneesDepayement, 'application/json')->post(
                        $requestToPayUrl, []);
                    //$body = $paymentResponse->body();

                    $end_date = Carbon::now();
                    //$date_end = $end_date->format('Y-m-d H:i:s');

                    $message = '';
                    if ($paymentResponse->successful()){
                        if($paymentResponse->status() === 202){
                            $payment->status = Payment::ACCEPTED;
                            $payment->end_date = $end_date;
                            $payment->save();
                            $message = 'The payment has been accepted. Please you need to approve the payment on your phone by dialing ' . env('MTN_USSD_PAYMENT_CODE');

                            //return response(array('success' => 1, 'faillure' => 0, 'response' => $message), 202);
                        }else{
                            $payment->status = Payment::SUCCESS;
                            $payment->end_date = $end_date;
                            $payment->save();
                            $message = 'The Payment processed successfully. Thank for using our service';
                            //return response(array('success' => 1, 'faillure' => 0, 'response' => $message), 200);
                        }

                        $header2 = [
                            'Authorization'                   => 'Bearer '.$accessToken,
                            'Ocp-Apim-Subscription-Key'       => $nkallaMtnAccountData['registration_key']['primary'],
                            'X-Target-Environment'            => $nkallaMtnAccountData['targetEnvironment'],
                            //'X-Reference-Id'=> $payment->id,
                            'Content-Type'=> 'application/json',
                        ];
                        $requestToPayUrl2 = $nkallaMtnAccountData['resource_endpoints']['base_url'].$nkallaMtnAccountData['resource_endpoints']['payment_path'].'/'.$payment->id;
                        /*Log::info("Request to Pay url: " . $requestToPayUrl);
                        Log::info("header: " . json_encode($header));
                        Log::info("body: " . json_encode($donneesDepayement));*/
                        $paymentResponse2 = Http::withHeaders($header2)/*->withBody($donneesDepayement, 'application/json')*/->get(
                            $requestToPayUrl2, []);
                        if ($paymentResponse2->successful()) {
                            if ($paymentResponse2->status() === 200) {
                                $payment->status = Payment::SUCCESS;
                                $payment->save();
                                $msg = json_encode($payment);
                                $privateKey = PrivateKey::fromFile('payment-private.pem');
                                $publickeyStr = file_get_contents('payment-public.pem');
                                $signature = $privateKey->sign($msg);
                                $signeData = [
                                    'public_key' => $publickeyStr,
                                    'signature' => $signature,
                                    'data' => $msg
                                ];
                                ProcessMessages::dispatch(
                                    env('LICENSE_PAYMENT_COMPLETED_EXCHANGE'),
                                    env('RABBIT_MQ_EXCHANGE_TYPE', 'fanout'),
                                    json_encode($signeData)
                                );
                                $message = "Payment processed successfully. Thanks for using our service";
                            }
                        }
                        return response(array('success' => 1, 'faillure' => 0, 'response' => $message), 200);
                    }else{
                        $payment->status = Payment::FAILED;
                        $payment->end_date = $end_date;
                        $payment->save();
                        $message = 'The payment failed due to:' . $paymentResponse->status();
                        return response(array('success' => 0, 'faillure' => 1, 'response' => $message), 200);
                    }


                    /*return response(array('success' => 1, 'faillure' => 0, 'response' => $paymentResponse->body(), 'status' => $paymentResponse->status(), 'token'=>$accessToken,
                        'url'=>$lofomboMtnAccountData['resource_endpoints']['base_url'].$lofomboMtnAccountData['resource_endpoints']['payment_path'],
                        'callbackurl' => url('/') . '/mtn-cameroon/' . $paymentid, 'message'=>$message), 200);*/
                }catch (\Exception $e){
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
                }

            }elseif ($paymentServiceProvider->paymentserviceprovidername === 'ORANGE CAMEROON'){
                return response(array('success' => 1, 'faillure' => 0, 'response' => "Under construction"), 200);
            }

        }elseif ($paymentServiceProvider->paymentmethodprovided === PaymentServiceProvider::CREDIT_CARD){
            $validator = Validator::make(
                $paymentData,
                [
                    'cardnumber' => 'required|string|min:1|max:100',
                    'holdername' => 'required|string|min:1|max:100',
                    'expirationdate' => 'required|string|min:1|max:10',
                    'securitycode' => 'required|string|min:1|max:10',
                ]
            );
            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }

        }
        elseif ($paymentServiceProvider->paymentmethodprovided === PaymentServiceProvider::LOFOMBO){
            $validator = Validator::make(
                $paymentData,
                [
                    'accountid' => 'required|string|min:1|max:100',
                    'holdername' => 'required|string|min:1|max:100',
                    'password' => 'required|string|min:8|max:100',
                    'pin' => 'required|string|min:4|max:4',
                ]
            );
            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }
        }
        return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Unknown Payment Method Provided'), 200);
    }

}
