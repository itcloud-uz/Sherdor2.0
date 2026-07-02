<?php

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\MachineWork;
use Illuminate\Http\Request;

class MachineWorkController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_owner_id' => 'required|exists:machine_owners,id',
            'machinery_id' => 'nullable|exists:machineries,id',
            'date' => 'required|date',
            'service_name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'paid' => 'required|numeric|min:0',
            'fuel' => 'required|numeric|min:0',
            'balance' => 'required|numeric',
            'note' => 'nullable|string',
        ]);

        MachineWork::create($validated);

        return back()->with('success', 'Ish muvaffaqiyatli saqlandi!');
    }

    public function update(Request $request, MachineWork $machineWork)
    {
        $validated = $request->validate([
            'machinery_id' => 'nullable|exists:machineries,id',
            'date' => 'required|date',
            'service_name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'paid' => 'required|numeric|min:0',
            'fuel' => 'required|numeric|min:0',
            'balance' => 'required|numeric',
            'note' => 'nullable|string',
        ]);

        $machineWork->update($validated);

        return back()->with('success', 'Ish muvaffaqiyatli yangilandi!');
    }

    public function destroy(MachineWork $machineWork)
    {
        $machineWork->delete();

        return back()->with('success', 'Ish muvaffaqiyatli o\'chirildi!');
    }
}
