<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::active()->orderBy('name')->get();

        return response()->json($cities);
    }

    public function show($id)
    {
        return response()->json(City::findOrFail($id));
    }
}