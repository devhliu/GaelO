<?php

namespace App\Http\Controllers;

use App\GaelO\Constants\Constants;
use App\GaelO\UseCases\Login\Login;
use App\GaelO\UseCases\Login\LoginRequest;
use App\GaelO\UseCases\Login\LoginResponse;
use App\GaelO\Util;
use App\Mail\UnconfirmedAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\User;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function login(Request $request, Login $login, LoginRequest $loginRequest, LoginResponse $loginResponse){

        $requestData = $request->all();
        $loginRequest = Util::fillObject($requestData, $loginRequest);
        $loginRequest->ip = $request->ip();

        $login->execute($loginRequest, $loginResponse);

        if($loginResponse->status === 200) {

            $user = User::where('username', $request->username)->first();
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;

            $token->save();

            return response()->json([
                'id' => $user->id,
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::parse($tokenResult->token->expires_at)->toDateTimeString()
            ], 200)->cookie('gaeloCookie', $tokenResult->accessToken);

        }else{
            if ($loginResponse->body === null) return response()->noContent()->setStatusCode($loginResponse->status, $loginResponse->statusText);
            else return response()->json($loginResponse->body)->setStatusCode($loginResponse->status, $loginResponse->statusText);
        }


    }

    public function logout(Request $request) {
        $request->user()->token()->revoke();
        return response()->json();
    }
}