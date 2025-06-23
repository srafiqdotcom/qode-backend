<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

abstract class Controller
{
    protected Request $request;

    //
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function validated($rules, $request)
    {
        $customMessages = [
            'required' => 'The :attribute field is required.'
        ];

        return Validator::make($request, $rules, $customMessages);

    }


}
