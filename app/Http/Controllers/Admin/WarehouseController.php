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
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

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
            'suppliers' => Supplier::with(['contracts', 'orders' => function($q) {
                $q->with('items')->oldest();
            }])->latest()->get(),
            'inventory_items' => InventoryItem::all()
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

        $token = config('services.telegram.pin_bot_token');
        $chatId = config('services.telegram.pin_chat_id');

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

    public function paySupplier(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,bank',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        DB::transaction(function () use ($validated, $supplier) {
            // Decrease supplier balance
            $supplier->decrement('balance', $validated['amount']);

            // Create transaction
            Transaction::create([
                'type' => 'expense',
                'amount' => $validated['amount'],
                'currency' => 'UZS',
                'payment_method' => $validated['payment_method'],
                'status' => 'approved',
                'supplier_id' => $supplier->id,
                'transaction_date' => $validated['transaction_date'],
                'notes' => $validated['notes'],
                'created_by' => auth()->id()
            ]);
        });

        return back()->with('success', 'Ta\'minotchiga to\'lov amalga oshirildi');
    }

    public function supplierLedgerPdf(Supplier $supplier)
    {
        $supplier->load(['orders' => function($q) {
            $q->where('status', 'delivered')->orderBy('order_date');
        }]);

        $payments = Transaction::where('supplier_id', $supplier->id)
                        ->where('type', 'expense')
                        ->orderBy('transaction_date')
                        ->get();

        $pdf = Pdf::loadView('exports.supplier_ledger', [
            'supplier' => $supplier,
            'orders' => $supplier->orders,
            'payments' => $payments
        ]);

        return $pdf->download('Taminotchi_hisoboti_' . $supplier->id . '.pdf');
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

    public function updateOrder(Request $request, PurchaseOrder $order)
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

        DB::transaction(function () use ($validated, $request, $order) {
            $isDelivered = $order->status === 'delivered';
            
            // Revert previous effects if delivered
            if ($isDelivered) {
                $supplier = $order->supplier;
                $supplier->decrement('balance', $order->total_amount);

                foreach ($order->items as $item) {
                    $invItem = InventoryItem::where('name', $item->item_name)->first();
                    if ($invItem) {
                        $invItem->decrement('quantity', $item->quantity);
                        InventoryTransaction::where('inventory_item_id', $invItem->id)
                            ->where('notes', 'Buyurtma orqali kirim (PO: ' . $order->order_number . ')')
                            ->delete();
                    }
                }
            }

            $total_amount = collect($validated['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            $order->update([
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $request->expected_delivery_date,
                'total_amount' => $total_amount,
                'notes' => $request->notes,
            ]);

            // Sync items
            $order->items()->delete();
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

            // Re-apply effects if it was delivered
            if ($isDelivered) {
                $order->load('items');
                $newSupplier = Supplier::find($validated['supplier_id']);
                $newSupplier->increment('balance', $total_amount);

                foreach ($order->items as $item) {
                    $invItem = InventoryItem::firstOrCreate(
                        ['name' => $item->item_name],
                        ['category' => $item->category ?? 'material', 'unit' => $item->unit, 'quantity' => 0, 'average_cost' => 0]
                    );

                    $oldTotal = $invItem->quantity * $invItem->average_cost;
                    $newTotal = $item->quantity * $item->unit_price;
                    $newQuantity = $invItem->quantity + $item->quantity;
                    $newAverage = $newQuantity > 0 ? ($oldTotal + $newTotal) / $newQuantity : 0;

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
            }
        });

        return back()->with('success', 'Buyurtma yangilandi');
    }

    public function destroyOrder(PurchaseOrder $order)
    {
        DB::transaction(function () use ($order) {
            if ($order->status === 'delivered') {
                $supplier = $order->supplier;
                $supplier->decrement('balance', $order->total_amount);

                foreach ($order->items as $item) {
                    $invItem = InventoryItem::where('name', $item->item_name)->first();
                    if ($invItem) {
                        $invItem->decrement('quantity', $item->quantity);
                        InventoryTransaction::where('inventory_item_id', $invItem->id)
                            ->where('notes', 'Buyurtma orqali kirim (PO: ' . $order->order_number . ')')
                            ->delete();
                    }
                }
            }

            $order->delete();
        });

        return back()->with('success', 'Buyurtma o\'chirildi');
    }

    public function downloadOrderPdf(PurchaseOrder $order)
    {
        $order->load(['supplier', 'items']);
        
        $pdf = Pdf::loadView('exports.purchase_order', ['order' => $order]);
        
        return $pdf->download('PO-' . $order->order_number . '.pdf');
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
