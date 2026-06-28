<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContractTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/ContractTemplates/Index', [
            'templates' => ContractTemplate::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:residential,commercial,custom',
            'description' => 'nullable|string',
            'content' => 'required|string',
        ]);

        ContractTemplate::create($validated);

        return redirect()->back()->with('message', 'Shablon muvaffaqiyatli yaratildi.');
    }

    public function update(Request $request, ContractTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:residential,commercial,custom',
            'description' => 'nullable|string',
            'content' => 'required|string',
        ]);

        $template->update($validated);

        return redirect()->back()->with('message', 'Shablon muvaffaqiyatli yangilandi.');
    }

    public function destroy(ContractTemplate $template)
    {
        $template->delete();

        return redirect()->back()->with('message', 'Shablon muvaffaqiyatli o\'chirildi.');
    }
}
