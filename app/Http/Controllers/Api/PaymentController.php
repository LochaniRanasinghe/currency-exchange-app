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
            $request->validated();

            // 2. Store the file in 'storage/app/public/uploads'
            $path = $request->file('file')->store('uploads', 'public');
            Log::info("File uploaded to: {$path}");

            if (!$path) {
                throw new \Exception("File could not be saved to disk.");
                Log::error("File upload failed.");
            }

            // Note: It is often safer to use Storage::path() for the full path
            $fullPath = storage_path('app/public/' . $path);

            Log::info("Full file path: {$fullPath}");

            // 3. Dispatch the Job
            ProcessPaymentCsv::dispatch($path);

            Log::info("Dispatched ProcessPaymentCsv job for file: {$fullPath}");

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
