<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ContractExport implements FromView, ShouldAutoSize
{
    protected $contract;
    protected $client;
    protected $unit;
    protected $template;

    public function __construct($contract, $client, $unit, $template = 'contracts.template')
    {
        $this->contract = $contract;
        $this->client = $client;
        $this->unit = $unit;
        $this->template = $template;
    }

    public function view(): View
    {
        return view($this->template, [
            'contract' => $this->contract,
            'client' => $this->client,
            'unit' => $this->unit,
        ]);
    }
}
