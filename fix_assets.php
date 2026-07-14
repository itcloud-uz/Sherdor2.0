$transactions = \App\Models\Transaction::where('type', 'expense')->whereNull('fixed_asset_id')->whereNotNull('category_id')->get();
$count = 0;
foreach ($transactions as $tx) {
    $cat = \App\Models\TransactionCategory::find($tx->category_id);
    if ($cat && $cat->target_type === 'fixed_asset') {
        $name = !empty($tx->notes) ? trim(explode('|', $tx->notes)[0]) : $cat->name;
        $asset = \App\Models\FixedAsset::create([
            'name' => $name,
            'type' => 'other',
            'purchase_date' => $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d') : $tx->created_at->format('Y-m-d'),
            'purchase_cost' => $tx->amount,
            'status' => 'active',
            'description' => $tx->notes ?? 'Tranzaksiyadan yaratildi.',
            'service_life_months' => 60
        ]);
        $tx->fixed_asset_id = $asset->id;
        $tx->save();
        $count++;
    }
}
echo "Fixed $count transactions.\n";
