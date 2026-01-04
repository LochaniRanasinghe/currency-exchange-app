<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Jobs\ProcessPaymentCsv;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UploadPaymentRequest;

class PaymentController extends Controller
{
    public function upload(UploadPaymentRequest $request)
    {
        try {
            $validated = $request->validated();

            $path = $request->file('file')->store('uploads', 's3');
            if (!$path) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The S3 driver returned false. Check your AWS credentials or bucket name.'
                ], 500);
            }

            ProcessPaymentCsv::dispatch($path);

            Log::info("File uploaded to S3 and Job dispatched: " . $path);

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded successfully to S3!',
                'path' => $path
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "An error occurred: {$e->getMessage()}"
            ], 500);
        }
    }
}
