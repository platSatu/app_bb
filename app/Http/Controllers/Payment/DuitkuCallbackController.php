<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\DuitkuCallbackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DuitkuCallbackController extends Controller
{
    public function __invoke(Request $request, DuitkuCallbackService $callbacks): Response
    {
        [$status, $body] = $callbacks->handle(
            $request->only(['merchantCode', 'amount', 'merchantOrderId', 'paymentCode', 'resultCode', 'reference', 'signature']),
            $request->ip(),
        );

        return response($body, $status);
    }
}
