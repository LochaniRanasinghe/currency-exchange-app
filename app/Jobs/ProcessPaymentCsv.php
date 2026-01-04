<?php

namespace App\Jobs;

use Exception;
use Carbon\Carbon;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessPaymentCsv implements ShouldQueue
{
    use Queueable;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    // public function handle()
    // {
    //     $file = fopen($this->filePath, 'r');
    //     $header = fgetcsv($file);

    //     while (($row = fgetcsv($file)) !== false) {
    //         $data = array_combine($header, $row);
            
    //         $apiKey = '00Ls8gb8Y8U9S5ZtDMTpuKp3GGz4G2Z8';
    //         $apiKey = 'lflHUYfwVidrkMfULq0PYx2iF9RrEVOG';
    //         $currency = strtoupper($data['currency']);
    //         $amount = (float)$data['amount'];

    //         // 1. Get latest rates with USD as the base
    //         $apiUrl = "https://api.apilayer.com/exchangerates_data/latest?base=USD&symbols={$currency}";

    //         $response = Http::withoutVerifying()
    //             ->withHeaders(['apikey' => $apiKey])
    //             ->get($apiUrl);

    //         $rate = 1; // Default fallback

    //         if ($response->successful()) {
    //             $result = $response->json();
    //             // The rate will be inside the 'rates' object
    //             $rate = $result['rates'][$currency] ?? 1;
    //         } else {
    //             Log::error("Failed to fetch latest rate for {$currency}");
    //         }

    //         // 2. Perform the math: Amount / Rate = USD
    //         // Example: 150 AUD / 1.5 (rate) = 100 USD
    //         $usdAmount = ($rate != 0) ? ($amount / $rate) : $amount;

    //         $transactionDate = Carbon::parse($data['date_time']);

    //         Payment::create([
    //             'customer_id'      => $data['customer_id'],
    //             'customer_name'    => $data['customer_name'],
    //             'customer_email'   => $data['customer_email'],
    //             'amount'           => $amount,
    //             'currency'         => $currency,
    //             'reference_no'     => $data['reference_no'],
    //             'transaction_date' => $transactionDate,
    //             'usd_amount'       => $usdAmount,
    //         ]);
    //     }
    //     fclose($file);
    //     unlink($this->filePath);
    // }


    public function handle()
    {
        // 1. Get the file content from S3 instead of local fopen
        // $this->filePath is the path returned by S3 (e.g., 'uploads/filename.csv')
        if (!Storage::disk('s3')->exists($this->filePath)) {
            Log::error("File not found on S3: {$this->filePath}");
            return;
        }

        $fileContent = Storage::disk('s3')->get($this->filePath);

        // 2. Convert string content into a stream so we can use fgetcsv
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $fileContent);
        rewind($stream);

        $file = $stream;
        $header = fgetcsv($file);
        
        // Track totals for a final log summary
        $successCount = 0;
        $failureCount = 0;

        while (($row = fgetcsv($stream)) !== false) {
            try {
                $data = array_combine($header, $row);
                $reference = $data['reference_no'] ?? 'Unknown';

                // --- Your API and Database Logic remains mostly the same ---
                $apiKey = 'n1tkcaKadZHqf0WSqhAeRDeLRFGgS2io';
                $currency = strtoupper($data['currency']);
                $amount = (float)$data['amount'];

                $apiUrl = "https://api.apilayer.com/exchangerates_data/latest?base=USD&symbols={$currency}";
                $response = Http::withoutVerifying()
                    ->withHeaders(['apikey' => $apiKey])
                    ->get($apiUrl);

                if (!$response->successful()) {
                    throw new Exception("API Error: " . $response->status());
                }

                $result = $response->json();
                $rate = $result['rates'][$currency] ?? null;

                if (!$rate) {
                    throw new Exception("Rate for {$currency} not found.");
                }

                $usdAmount = $amount / $rate;
                $transactionDate = Carbon::parse($data['date_time']);

                Payment::create([
                    'customer_id'      => $data['customer_id'],
                    'customer_name'    => $data['customer_name'],
                    'customer_email'   => $data['customer_email'],
                    'amount'           => $amount,
                    'currency'         => $currency,
                    'reference_no'     => $reference,
                    'transaction_date' => $transactionDate,
                    'usd_amount'       => $usdAmount,
                ]);

                Log::info("Row Processed: Reference {$reference}");
                $successCount++;

            } catch (Exception $e) {
                Log::error("Row Failed: " . ($data['reference_no'] ?? 'N/A') . ". Error: " . $e->getMessage());
                $failureCount++;
                continue;
            }
        }

        fclose($stream);

        // 3. Clean up: Delete the file from S3 after processing (optional but good practice)
        Storage::disk('s3')->delete($this->filePath);

        Log::info("S3 CSV Processing Complete. Success: {$successCount}, Fail: {$failureCount}");
    }
}
