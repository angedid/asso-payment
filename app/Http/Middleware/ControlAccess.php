<?php

namespace App\Http\Middleware;

use App\Domain\Model\AccessToken;
use App\Http\Controllers\GlobalResultHandler;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ControlAccess
{
    public function checkAccessTokenValidity(AccessToken $accessToken) : bool{
        $expiresAt = new Carbon($accessToken->expires_at);
        $now = Carbon::now();
        if ($expiresAt->isBefore($now)) {
            return false;
        }
        return !$accessToken->revoked;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $access_token = $request->bearerToken();
        if ($access_token !== null) {
            Log::info('access token: ' . $access_token);
            $tokendata = explode('.', $access_token);
            Log::info("Tokendata: " . base64_decode($tokendata[1]));
            $jsondata = json_decode(base64_decode($tokendata[1]));
            $user_id = $jsondata->sub;
            $jti = $jsondata->jti;
            $accessToken = AccessToken::where('id', $jti)->first();
            if ($accessToken !== null) {
                if ($this->checkAccessTokenValidity($accessToken)) {
                    return $next($request);
                }else{
                    return response(GlobalResultHandler::buildFaillureReasonArray('Token with jti: ' .$jti.' not valid'), 200);
                }
                //Log::info("Access token found: $accessToken");

            }/*else{
                return response(GlobalResultHandler::buildFaillureReasonArray(__("Vous n'etes pas autorise")), 200);
            }*/
            //$user = User::where("id", $user_id)->first();
            //$user = $request->user();
            //Log::info("User ID: $user");

        }

        return $next($request);
    }
}
