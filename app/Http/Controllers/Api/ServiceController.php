<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{

    public function store(Request $request)
    {

        $service = Service::create($request->all());

        return response()->json([
            'success'=>true,
            'service'=>$service
        ]);
    }


    public function update(Request $request, Service $service)
    {

        $service->update($request->all());

        return response()->json([
            'success'=>true
        ]);
    }


    public function destroy(Service $service)
    {

        $service->delete();

        return response()->json([
            'success'=>true
        ]);
    }

}