<?php

namespace App\Jobs;

use Exception;
use Carbon\Carbon;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessPaymentCsv implements ShouldQueue
{
    use Queueable;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $disk;

    public function __construct($filePath, $disk = 'public')
    {
        $this->filePath = $filePath;
        $this->disk = $disk;
    }
    public function handle()
    {
        // Get file content from storage (S3 or local)
        if (!Storage::disk($this->disk)->exists($this->filePath)) {
            Log::error("File not found: {$this->filePath}");
            throw new Exception("File not found: {$this->filePath}");
        }

        $fileContent = Storage::disk($this->disk)->get($this->filePath);
        $lines = explode("\n", $fileContent);
        $header = str_getcsv(array_shift($lines));

        // Track totals for a final log summary
        $successCount = 0;
        $failureCount = 0;

        foreach ($lines as $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                continue;
            }

            try {
                $row = str_getcsv($line);
                $data = array_combine($header, $row);
                $reference = $data['reference_no'] ?? 'Unknown';

                // 1. Fetch Exchange Rate
                $apiKey = env('EXCHANGE_RATE_API_KEY');
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
                    throw new Exception("Rate for {$currency} not found in API response.");
                }

                // 2. Calculation
                $usdAmount = $amount / $rate;
                $transactionDate = Carbon::parse($data['date_time']);

                // 3. Store in Database [cite: 17]
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

                // 4. Log Success for the row [cite: 18]
                Log::info("Row Processed Successfully: Reference {$reference}");
                $successCount++;
            } catch (Exception $e) {
                // 5. Log Failure for the row without stopping the loop [cite: 18, 19]
                Log::error("Row Processing Failed: Reference " . ($data['reference_no'] ?? 'N/A') . ". Error: " . $e->getMessage());
                $failureCount++;
                continue; // Move to the next row
            }
        }

        // Delete the file from storage after processing
        Storage::disk($this->disk)->delete($this->filePath);

        // Final Summary Log
        Log::info("CSV Processing Complete. Successes: {$successCount}, Failures: {$failureCount}");
    }
}
