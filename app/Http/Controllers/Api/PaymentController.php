<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Jobs\ProcessPaymentCsv;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPaymentRequest;

class PaymentController extends Controller
{
    public function upload(UploadPaymentRequest $request)
    {
        try {
            // 1. Validate the request
            $validated = $request->validated();

            $path = $request->file('file')->store('uploads', 's3');            // ProcessPaymentCsv::dispatch($path);

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded to AWS S3 and processing started via SQS.' 
            ], 202);
        } catch (\Exception $e) {

            Log::error("Upload Failed: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => "An error occurred: {$e->getMessage()}"
            ], 500);
        }
    }
}
