<?php
/**
 * Created by PhpStorm.
 * User: el
 * Date: 9/20/18
 * Time: 11:03 AM
 */

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GlobalResultHandler
{
    public static function  buildSuccesResponseArray($response){
        return array('success' => 1, 'faillure' => 0, 'response' => $response);
    }

    public static function  buildFaillureReasonArray($raison){

        return array('success' => 0, 'faillure' => 1, 'raison' => $raison);

    }

}
