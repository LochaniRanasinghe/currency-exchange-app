<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Jobs\ProcessPaymentCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPaymentRequest;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function upload(UploadPaymentRequest $request)
    {
        try {
            // 1. Validate the request
            $validated = $request->validated();

            // 2. Store the file on S3 (or configured disk)
            $disk = config('filesystems.default');
            $path = $request->file('file')->store('uploads/payments', $disk);

            // 3. Dispatch the Job with the S3 path
            ProcessPaymentCsv::dispatch($path, $disk);

            return response()->json([
                'status' => 'success',
                'message' => 'File is being processed in the background.'
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "An error occurred: {$e->getMessage()}"
            ], 500);
        }
    }
}
