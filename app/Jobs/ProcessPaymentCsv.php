<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
            
    //         // Use the Header-based approach as shown in your documentation
    //         $apiKey = '00Ls8gb8Y8U9S5ZtDMTpuKp3GGz4G2Z8';
    //         $from = strtoupper($data['currency']);
    //         $amount = (float)$data['amount'];

    //         // Use the /convert endpoint for accuracy
    //         $apiUrl = "https://api.apilayer.com/exchangerates_data/convert?to=USD&from={$from}&amount={$amount}";

    //         $response = Http::withoutVerifying()
    //             ->withHeaders(['apikey' => $apiKey]) // Key sent in Header per docs
    //             ->get($apiUrl);

    //         if ($response->successful()) {
    //             $result = $response->json();
    //             // The API returns the final calculated value in the 'result' field
    //             $usdAmount = $result['result'] ?? $amount; 
    //         } else {
    //             // Fallback: If API fails, log error and keep original amount
    //             Log::error("Currency API failed for {$from}. Row: " . $data['reference_no']);
    //             $usdAmount = $amount; 
    //         }

    //         $transactionDate = Carbon::parse($data['date_time']);

    //         Payment::create([
    //             'customer_id'      => $data['customer_id'],
    //             'customer_name'    => $data['customer_name'],
    //             'customer_email'   => $data['customer_email'],
    //             'amount'           => $amount,
    //             'currency'         => $from,
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
        $file = fopen($this->filePath, 'r');
        $header = fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            
            $apiKey = '00Ls8gb8Y8U9S5ZtDMTpuKp3GGz4G2Z8';
            $currency = strtoupper($data['currency']);
            $amount = (float)$data['amount'];

            // 1. Get latest rates with USD as the base
            $apiUrl = "https://api.apilayer.com/exchangerates_data/latest?base=USD&symbols={$currency}";

            $response = Http::withoutVerifying()
                ->withHeaders(['apikey' => $apiKey])
                ->get($apiUrl);

            $rate = 1; // Default fallback

            if ($response->successful()) {
                $result = $response->json();
                // The rate will be inside the 'rates' object
                $rate = $result['rates'][$currency] ?? 1;
            } else {
                Log::error("Failed to fetch latest rate for {$currency}");
            }

            // 2. Perform the math: Amount / Rate = USD
            // Example: 150 AUD / 1.5 (rate) = 100 USD
            $usdAmount = ($rate != 0) ? ($amount / $rate) : $amount;

            $transactionDate = Carbon::parse($data['date_time']);

            Payment::create([
                'customer_id'      => $data['customer_id'],
                'customer_name'    => $data['customer_name'],
                'customer_email'   => $data['customer_email'],
                'amount'           => $amount,
                'currency'         => $currency,
                'reference_no'     => $data['reference_no'],
                'transaction_date' => $transactionDate,
                'usd_amount'       => $usdAmount,
            ]);
        }
        fclose($file);
        unlink($this->filePath);
    }
}
