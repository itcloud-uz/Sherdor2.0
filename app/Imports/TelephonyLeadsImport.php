<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TelephonyLeadsImport implements ToCollection
{
    protected $campaign_id;

    public function __construct($campaign_id)
    {
        $this->campaign_id = $campaign_id;
    }

    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $phone = $row[0]; // Assuming phone number is in the first column
            if (!empty($phone)) {
                \App\Models\TelephonyLead::create([
                    'campaign_id' => $this->campaign_id,
                    'phone_number' => (string)$phone,
                    'status' => 'pending',
                ]);
            }
        }
    }
}
