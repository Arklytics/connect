<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $db = \Database::connect();
        $bizId = (int) $request->session()->get('biz_id');
        \PaymentSupport::ensureTables($db);

        return view('business.payments.index', [
            'packages' => \PaymentSupport::packages($db),
            'packageStatus' => \PaymentSupport::businessPackageStatus($db, $bizId),
            'razorpayKeyId' => \PaymentSupport::razorpayKeyId(),
        ]);
    }

    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'package_key' => ['required', 'string', 'max:50'],
        ]);

        $result = \PaymentSupport::createRazorpayOrder(
            \Database::connect(),
            (int) $request->session()->get('biz_id'),
            (string) $data['package_key']
        );

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function verify(Request $request)
    {
        $result = \PaymentSupport::verifyAndActivate(
            \Database::connect(),
            (int) $request->session()->get('biz_id'),
            $request->all()
        );

        return response()->json($result + [
            'redirect_url' => route('business.dashboard'),
        ], ($result['ok'] ?? false) ? 200 : 422);
    }
}
