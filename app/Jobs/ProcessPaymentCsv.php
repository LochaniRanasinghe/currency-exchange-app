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


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Job Started: Processing CSV at {$this->filePath}");
        // 1. Check if file exists in public disk
        $fullPath = storage_path('app/public/' . $this->filePath);
        if (!file_exists($fullPath)) {
            Log::error("Job Failed: File not found at {$fullPath}");
            return;
        }

        $stream = fopen($fullPath, 'r');
        if (!$stream) {
            Log::error("Job Failed: Could not open stream for {$fullPath}");
            return;
        }

        $header = fgetcsv($stream);

        $successCount = 0;
        $failureCount = 0;

        $cachedRates = [];
        $apiKey = '994orjUPsojC7HM0h3QhQZSpcEOYcLmV';

        while (($row = fgetcsv($stream)) !== false) {
            try {
                $data = array_combine($header, $row);
                $currency = strtoupper($data['currency']); // e.g., "EUR"
                $amount = (float)$data['amount'];
                $reference = $data['reference_no'] ?? 'N/A';

                Log::info("Processing reference: {$reference} with amount: {$amount} {$currency}");

                // 3. Fetch Exchange Rate (using $currency as the 'symbols' parameter)
                if (!isset($cachedRates[$currency])) {
                    Log::info("Fetching fresh rate for {$currency} from API...");

                    $apiUrl = "https://api.apilayer.com/exchangerates_data/latest";

                    $response = Http::withHeaders(['apikey' => $apiKey])
                        ->withoutVerifying()
                        ->get($apiUrl, [
                            'symbols' => $currency,
                            'base'    => 'USD',
                        ]);

                    Log::info("API Response Status: " . $response);

                    if (!$response->successful()) {
                        // Throw the exception first; the catch block handles the Log::error
                        throw new Exception("API Error {$response->status()}: " . $response->body());
                    }

                    $result = $response->json();

                    // Verify the rate exists in the response
                    if (!isset($result['rates'][$currency])) {
                        throw new Exception("Currency {$currency} not found in API response.");
                    }

                    $cachedRates[$currency] = $result['rates'][$currency];
                }

                $rate = $cachedRates[$currency];

                // 4. Calculations
                $usdAmount = $amount / $rate;
                $transactionDate = Carbon::parse($data['date_time']);

                // 5. Save to Database
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

                $successCount++;
                Log::info("Successfully processed reference: {$reference}");

            } catch (Exception $e) {
                $failureCount++;
                Log::error("Row Error | Reference: " . ($data['reference_no'] ?? 'N/A') . " | " . $e->getMessage());
            }
        }

        fclose($stream);

        Log::info("CSV Processing Complete. Success: {$successCount}, Fail: {$failureCount}");
    }
}
