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

    public function handle()
    {
        $file = fopen($this->filePath, 'r');
        $header = fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            $apiKey = '00Ls8gb8Y8U9S5ZtDMTpuKp3GGz4G2Z8';
            $currency = strtoupper($data['currency']);

            // Correct API URL for live rates
            $apiUrl = "https://api.exchangerate.host/live?access_key={$apiKey}&source={$currency}&currencies=USD";
            $response = Http::withoutVerifying()->get($apiUrl);

            // Initialize rate
            $rate = 2;

            if ($response->successful()) {
                $result = $response->json();
                // The 'live' endpoint returns results in a 'quotes' array
                // Format is usually SOURCE + TARGET (e.g., AUDUSD)
                $rate = $result['quotes']["{$currency}USD"] ?? 2;
            } else {
                Log::error("Currency API failed for {$currency}");
            }

            // Calculation using the verified rate
            $usdAmount = (float)$data['amount'] * $rate;
            $transactionDate = Carbon::parse($data['date_time']);

            Payment::create([
                'customer_id'      => $data['customer_id'],
                'customer_name'    => $data['customer_name'],
                'customer_email'   => $data['customer_email'],
                'amount'           => $data['amount'],
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
