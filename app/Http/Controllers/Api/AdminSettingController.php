<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminSettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::allAsObject());
    }

    public function update(Request $request)
    {
        $allowed = [
            'platform_name', 'contact_email', 'support_phone',
            'commission_rate', 'min_withdrawal', 'wompi_public_key',
            'alerts_email', 'maintenance_mode', 'allow_registration',
            'allow_pro_registration', 'email_on_pro_approved', 'email_on_completed',
        ];

        foreach ($allowed as $key) {
            if ($request->has($key)) {
                $val = $request->input($key);
                // Normalize booleans
                Setting::set($key, is_bool($val) ? ($val ? '1' : '0') : $val);
            }
        }

        AdminLog::record(Auth::user(), 'update', 'settings', null, 'Actualizó la configuración de la plataforma');

        return response()->json(['success' => true, 'settings' => Setting::allAsObject()]);
    }
}
