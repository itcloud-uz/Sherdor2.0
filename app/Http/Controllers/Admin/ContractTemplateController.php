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
            'templates' => ContractTemplate::with('variantGroups.variants')->latest()->get()
        ]);
    }

    public function detect(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'docx') {
                        $fail('Fayl faqat .docx formatida bo\'lishi kerak.');
                    }
                }
            ]
        ]);

        $file = $request->file('file');
        $tempPath = $file->store('temp');
        $absolutePath = storage_path('app/' . $tempPath);

        try {
            // Detect placeholders
            $placeholders = json_decode(ContractTemplate::runPython('detect "' . $absolutePath . '"'), true);

            // Render preview HTML
            $previewData = json_decode(ContractTemplate::runPython('preview "' . $absolutePath . '"'), true);

            @unlink($absolutePath);

            return response()->json([
                'placeholders' => $placeholders,
                'preview_html' => $previewData['html'] ?? '',
                'warnings' => $previewData['warnings'] ?? []
            ]);
        } catch (\Exception $e) {
            @unlink($absolutePath);
            return response()->json([
                'message' => 'Faylni tahlil qilishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        if (is_string($request->placeholders)) {
            $request->merge(['placeholders' => json_decode($request->placeholders, true)]);
        }
        if (is_string($request->variant_groups)) {
            $request->merge(['variant_groups' => json_decode($request->variant_groups, true)]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:residential,commercial,custom',
            'description' => 'nullable|string',
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'docx') {
                        $fail('Fayl faqat .docx formatida bo\'lishi kerak.');
                    }
                }
            ],
            'placeholders' => 'required|array',
            'variant_groups' => 'nullable|array'
        ]);

        $file = $request->file('file');
        
        // 1. Save the original uploaded file
        $originalFilename = 'tpl_orig_' . time() . '_' . uniqid() . '.docx';
        $originalPath = $file->storeAs('contract_templates', $originalFilename);
        $absoluteOriginalPath = storage_path('app/' . $originalPath);

        // 2. Prepare the replacements JSON
        $replacements = [];
        foreach ($request->placeholders as $c) {
            if (!empty($c['key']) && !empty($c['text']) && ($c['confirmed'] ?? true)) {
                $replacements[$c['text']] = $c['key'];
            }
        }

        // 3. Apply replacements using python to create the actual template file
        $templateFilename = 'tpl_' . time() . '_' . uniqid() . '.docx';
        $templatePath = 'contract_templates/' . $templateFilename;
        $absoluteTemplatePath = storage_path('app/' . $templatePath);

        $replJson = json_encode($replacements, JSON_UNESCAPED_UNICODE);
        $tempReplFile = tempnam(sys_get_temp_dir(), 'docx_repl_');
        file_put_contents($tempReplFile, $replJson);

        try {
            ContractTemplate::runPython('replace "' . $absoluteOriginalPath . '" "' . addslashes($tempReplFile) . '" "' . $absoluteTemplatePath . '"');
            @unlink($tempReplFile);

            // 4. Generate the HTML preview from the processed template
            $previewData = json_decode(ContractTemplate::runPython('preview "' . $absoluteTemplatePath . '"'), true);
            $previewHtml = $previewData['html'] ?? '';
        } catch (\Exception $e) {
            @unlink($tempReplFile);
            @unlink($absoluteOriginalPath);
            return back()->withErrors(['message' => 'Shablon yaratishda xatolik: ' . $e->getMessage()]);
        }

        // Clean up original file
        @unlink($absoluteOriginalPath);

        // 5. Create ContractTemplate
        $template = ContractTemplate::create([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'docx_path' => $templatePath,
            'preview_html' => $previewHtml,
            'placeholders' => array_values(array_unique(array_values($replacements)))
        ]);

        // 6. Create Variant Groups & Variants
        if ($request->has('variant_groups')) {
            foreach ($request->variant_groups as $g) {
                if (empty($g['anchor']) || empty($g['label'])) continue;
                
                $group = $template->variantGroups()->create([
                    'anchor' => $g['anchor'],
                    'label' => $g['label']
                ]);

                if (!empty($g['variants'])) {
                    foreach ($g['variants'] as $v) {
                        if (empty($v['key']) || empty($v['label']) || empty($v['content'])) continue;
                        
                        $group->variants()->create([
                            'key' => $v['key'],
                            'label' => $v['label'],
                            'content' => $v['content'],
                            'placeholders' => $v['placeholders'] ?? []
                        ]);
                    }
                }
            }
        }

        return redirect()->route('admin.contract-templates.index')->with('message', 'Shablon muvaffaqiyatli yaratildi.');
    }

    public function update(Request $request, ContractTemplate $template)
    {
        if (is_string($request->placeholders)) {
            $request->merge(['placeholders' => json_decode($request->placeholders, true)]);
        }
        if (is_string($request->variant_groups)) {
            $request->merge(['variant_groups' => json_decode($request->variant_groups, true)]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:residential,commercial,custom',
            'description' => 'nullable|string',
            'file' => [
                'nullable',
                'file',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'docx') {
                        $fail('Fayl faqat .docx formatida bo\'lishi kerak.');
                    }
                }
            ],
            'placeholders' => 'nullable|array',
            'variant_groups' => 'nullable|array'
        ]);

        $template->update([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description
        ]);

        if ($request->hasFile('file')) {
            // Delete old file
            if ($template->docx_path && file_exists(storage_path('app/' . $template->docx_path))) {
                @unlink(storage_path('app/' . $template->docx_path));
            }

            // Save new original file
            $file = $request->file('file');
            $originalFilename = 'tpl_orig_' . time() . '_' . uniqid() . '.docx';
            $originalPath = $file->storeAs('contract_templates', $originalFilename);
            $absoluteOriginalPath = storage_path('app/' . $originalPath);

            // Prepare replacements
            $replacements = [];
            if ($request->has('placeholders')) {
                foreach ($request->placeholders as $c) {
                    if (!empty($c['key']) && !empty($c['text']) && ($c['confirmed'] ?? true)) {
                        $replacements[$c['text']] = $c['key'];
                    }
                }
            }

            // Create new processed template
            $templateFilename = 'tpl_' . time() . '_' . uniqid() . '.docx';
            $templatePath = 'contract_templates/' . $templateFilename;
            $absoluteTemplatePath = storage_path('app/' . $templatePath);

            $replJson = json_encode($replacements, JSON_UNESCAPED_UNICODE);
            $tempReplFile = tempnam(sys_get_temp_dir(), 'docx_repl_');
            file_put_contents($tempReplFile, $replJson);

            try {
                ContractTemplate::runPython('replace "' . $absoluteOriginalPath . '" "' . addslashes($tempReplFile) . '" "' . $absoluteTemplatePath . '"');
                @unlink($tempReplFile);

                // Generate preview
                $previewData = json_decode(ContractTemplate::runPython('preview "' . $absoluteTemplatePath . '"'), true);
                $previewHtml = $previewData['html'] ?? '';
            } catch (\Exception $e) {
                @unlink($tempReplFile);
                @unlink($absoluteOriginalPath);
                return back()->withErrors(['message' => 'Shablon tahrirlashda xatolik: ' . $e->getMessage()]);
            }

            @unlink($absoluteOriginalPath);

            $template->update([
                'docx_path' => $templatePath,
                'preview_html' => $previewHtml,
                'placeholders' => array_values(array_unique(array_values($replacements)))
            ]);
        }

        // Sync variant groups and variants
        $template->variantGroups()->delete();

        if ($request->has('variant_groups')) {
            foreach ($request->variant_groups as $g) {
                if (empty($g['anchor']) || empty($g['label'])) continue;
                
                $group = $template->variantGroups()->create([
                    'anchor' => $g['anchor'],
                    'label' => $g['label']
                ]);

                if (!empty($g['variants'])) {
                    foreach ($g['variants'] as $v) {
                        if (empty($v['key']) || empty($v['label']) || empty($v['content'])) continue;
                        
                        $group->variants()->create([
                            'key' => $v['key'],
                            'label' => $v['label'],
                            'content' => $v['content'],
                            'placeholders' => $v['placeholders'] ?? []
                        ]);
                    }
                }
            }
        }

        return redirect()->route('admin.contract-templates.index')->with('message', 'Shablon muvaffaqiyatli yangilandi.');
    }

    public function destroy(ContractTemplate $template)
    {
        if ($template->docx_path && file_exists(storage_path('app/' . $template->docx_path))) {
            @unlink(storage_path('app/' . $template->docx_path));
        }
        $template->delete();

        return redirect()->back()->with('message', 'Shablon muvaffaqiyatli o\'chirildi.');
    }
}
