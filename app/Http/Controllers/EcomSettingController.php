<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\EcomSetting;
use Illuminate\Http\Request;

class EcomSettingController extends Controller
{
    
   
    public function updateSingleecomSetting(Request $request)
    {
        try {
            $ecomSetting = EcomSetting::find(1);
              
            $ecomSetting->update([
                'IsActive' => $request->input('isActive'),
            ]);
            $converted = arrayKeysToCamelCase($ecomSetting->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating EcomSetting. Please try again later.'], 500);
        }
    }
    
}
