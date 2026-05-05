<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Supplier;
use App\Models\SupplierContract;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function index()
    {
        $stats = [
            'total_items' => InventoryItem::count(),
            'total_value' => InventoryItem::sum(DB::raw('quantity * average_cost')),
            'low_stock' => InventoryItem::where('quantity', '<', 10)->count(),
            'active_suppliers' => Supplier::count(),
            'pending_orders' => PurchaseOrder::where('status', 'pending')->count(),
        ];

        $recent_transactions = InventoryTransaction::with(['item', 'user'])
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Admin/Warehouse/Index', [
            'stats' => $stats,
            'recent_transactions' => $recent_transactions
        ]);
    }

    public function inventory()
    {
        return Inertia::render('Admin/Warehouse/Inventory', [
            'inventory' => InventoryItem::all(),
            'transactions' => InventoryTransaction::with(['item', 'user'])->latest()->get()
        ]);
    }

    public function suppliers()
    {
        return Inertia::render('Admin/Warehouse/Suppliers', [
            'suppliers' => Supplier::with('contracts')->latest()->get()
        ]);
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'inn' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'mfo' => 'nullable|string',
        ]);

        Supplier::create($validated);
        return back()->with('success', 'Ta\'minotchi qo\'shildi');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'inn' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'mfo' => 'nullable|string',
        ]);

        $supplier->update($validated);
        return back()->with('success', 'Ta\'minotchi yangilandi');
    }

    public function destroySupplier(Supplier $supplier)
    {
        if ($supplier->balance != 0) {
            return back()->with('error', 'Balansida mablag\' bo\'lgan ta\'minotchini o\'chirib bo\'lmaydi');
        }
        
        $supplier->delete();
        return back()->with('success', 'Ta\'minotchi o\'chirildi');
    }

    public function uploadSupplierDocument(Request $request, Supplier $supplier)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
        ]);

        $file = $request->file('document');
        $filename = 'supplier_doc_' . $supplier->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('supplier_documents', $filename, 'public');

        // Delete old document if exists
        if ($supplier->document_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($supplier->document_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($supplier->document_path);
        }

        $supplier->update(['document_path' => $path]);

        return back()->with('success', 'Hujjat muvaffaqiyatli biriktirildi');
    }

    public function sendSupplierDocumentToTelegram(Supplier $supplier)
    {
        if (!$supplier->document_path) {
            return back()->with('error', 'Yuborish uchun hujjat mavjud emas');
        }

        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            return back()->with('error', 'Telegram Bot sozlamalari (.env) kiritilmagan');
        }

        $filePath = storage_path('app/public/' . $supplier->document_path);

        if (!file_exists($filePath)) {
            return back()->with('error', 'Hujjat fayli topilmadi');
        }

        $caption = "🏢 Ta'minotchi: " . ($supplier->company_name ?: $supplier->name) . "\n"
                 . "👤 Vakil: " . $supplier->name . "\n"
                 . "📞 Tel: " . $supplier->phone . "\n"
                 . "💼 STIR (INN): " . $supplier->inn . "\n\n"
                 . "Biriktirilgan shartnoma yoki shot-faktura.";

        $response = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->attach('document', file_get_contents($filePath), basename($filePath))
            ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                'chat_id' => $chatId,
                'caption' => $caption
            ]);

        if ($response->successful()) {
            return back()->with('success', 'Hujjat Telegramga yuborildi');
        }

        return back()->with('error', 'Telegramga yuborishda xatolik: ' . $response->body());
    }

    public function contracts()
    {
        return Inertia::render('Admin/Warehouse/Contracts', [
            'contracts' => SupplierContract::with('supplier')->latest()->get(),
            'suppliers' => Supplier::all()
        ]);
    }

    public function storeContract(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'contract_number' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        SupplierContract::create($validated);

        return back()->with('success', 'Ta\'minot shartnomasi muvaffaqiyatli saqlandi');
    }

    public function scanContract(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:10240'
        ]);

        $file = $request->file('document');
        $path = $file->storeAs('temp_scans', 'scan_' . time() . '.pdf', 'local');
        $absolutePath = storage_path('app/' . $path);

        $scriptPath = base_path('scripts/pdf_text_parser.py');
        
        $command = escapeshellcmd("python \"{$scriptPath}\" \"{$absolutePath}\"");
        $output = shell_exec($command);
        
        // Clean up temp file
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        if (!$output) {
            return response()->json(['success' => false, 'message' => 'Skript ishga tushmadi.']);
        }

        $result = json_decode($output, true);

        if (isset($result['status']) && $result['status'] === 'success') {
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'is_json' => true
            ]);
        }

        return response()->json([
            'success' => false, 
            'message' => $result['message'] ?? 'Fayldan ma\'lumot o\'qib bo\'lmadi.'
        ]);
    }

    public function orders()
    {
        return Inertia::render('Admin/Warehouse/Orders', [
            'orders' => PurchaseOrder::with(['supplier', 'items'])->latest()->get(),
            'suppliers' => Supplier::all(),
            'inventory_items' => InventoryItem::all()
        ]);
    }

    public function storeOrder(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'items' => 'required|array',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit_price' => 'required|numeric',
            'items.*.unit' => 'required|string',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $total_amount = collect($validated['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            $order = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'order_number' => 'PO-' . time(),
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $request->expected_delivery_date,
                'total_amount' => $total_amount,
                'notes' => $request->notes,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'item_name' => $item['item_name'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        });

        return back()->with('success', 'Buyurtma rasmiylashtirildi');
    }

    public function receiveOrder($id)
    {
        $order = PurchaseOrder::with('items')->findOrFail($id);
        
        if ($order->status === 'delivered') {
            return back()->with('error', 'Bu buyurtma qabul qilingan');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'delivered']);
            
            // Add debt to supplier
            $supplier = $order->supplier;
            $supplier->increment('balance', $order->total_amount);

            // Add items to inventory
            foreach ($order->items as $item) {
                $invItem = InventoryItem::firstOrCreate(
                    ['name' => $item->item_name],
                    ['category' => $item->category, 'unit' => $item->unit, 'quantity' => 0, 'average_cost' => 0]
                );
                
                // Recalculate average cost
                $oldTotal = $invItem->quantity * $invItem->average_cost;
                $newTotal = $item->quantity * $item->unit_price;
                $newQuantity = $invItem->quantity + $item->quantity;
                $newAverage = ($oldTotal + $newTotal) / $newQuantity;

                $invItem->update([
                    'quantity' => $newQuantity,
                    'average_cost' => $newAverage
                ]);

                InventoryTransaction::create([
                    'inventory_item_id' => $invItem->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'cost' => $item->unit_price,
                    'notes' => 'Buyurtma orqali kirim (PO: ' . $order->order_number . ')',
                    'user_id' => auth()->id()
                ]);
            }
        });

        return back()->with('success', 'Buyurtma qabul qilindi. Mahsulotlar omborga tushdi va Ta\'minotchi balansi yangilandi.');
    }
}
