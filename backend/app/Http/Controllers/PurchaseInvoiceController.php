<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        try {
            $invoices = PurchaseInvoice::with(['fournisseur', 'items.product'])
                ->where('user_id', auth()->id())
                ->latest()
                ->get();

            return response()->json($invoices);
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoice index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de la récupération des factures d\'achat.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'fournisseur_id' => 'required|exists:fournisseurs,id',
            'date' => 'required|date',
            'tva_rate' => 'required|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $invoiceNumber = $this->generateInvoiceNumber();

            $totalHt = collect($request->items)->sum(fn($i) => $i['quantity'] * $i['unit_price']);
            $totalTtc = $totalHt * (1 + $request->tva_rate / 100);

            $invoice = PurchaseInvoice::create([
                'user_id' => auth()->id(),
                'fournisseur_id' => $request->fournisseur_id,
                'invoice_number' => $invoiceNumber,
                'date' => $request->date,
                'total_ht' => round($totalHt, 2),
                'tva_rate' => $request->tva_rate,
                'total_ttc' => round($totalTtc, 2),
                'status' => 'impayé',
            ]);

            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => round($itemTotal, 2),
                ]);

                Product::where('id', $item['product_id'])->increment('stock', $item['quantity']);
            }

            DB::commit();

            return response()->json($invoice->load(['fournisseur', 'items.product']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PurchaseInvoice store error: ' . $e->getMessage(), ['input' => $request->all(), 'exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la facture d\'achat.',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $invoice = PurchaseInvoice::with(['fournisseur', 'items.product'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json($invoice);
        } catch (\Throwable $e) {
            Log::error("PurchaseInvoice show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Facture d\'achat introuvable.',
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $invoice = PurchaseInvoice::where('user_id', auth()->id())->findOrFail($id);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Facture d\'achat introuvable.',
            ], 404);
        }

        $request->validate([
            'fournisseur_id' => 'sometimes|exists:fournisseurs,id',
            'date' => 'sometimes|date',
            'tva_rate' => 'sometimes|numeric|min:0|max:100',
            'status' => 'sometimes|in:payé,impayé',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $invoice->update($request->only(['fournisseur_id', 'date', 'tva_rate', 'status']));

            if ($request->has('items')) {
                foreach ($invoice->items as $oldItem) {
                    Product::where('id', $oldItem->product_id)->decrement('stock', $oldItem->quantity);
                }

                $invoice->items()->delete();

                $totalHt = 0;
                foreach ($request->items as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    $totalHt += $itemTotal;
                    PurchaseInvoiceItem::create([
                        'purchase_invoice_id' => $invoice->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total' => round($itemTotal, 2),
                    ]);
                    Product::where('id', $item['product_id'])->increment('stock', $item['quantity']);
                }

                $totalTtc = $totalHt * (1 + $invoice->tva_rate / 100);
                $invoice->update([
                    'total_ht' => round($totalHt, 2),
                    'total_ttc' => round($totalTtc, 2),
                ]);
            }

            DB::commit();

            return response()->json($invoice->load(['fournisseur', 'items.product']));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("PurchaseInvoice update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $invoice = PurchaseInvoice::where('user_id', auth()->id())->findOrFail($id);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Facture d\'achat introuvable.',
            ], 404);
        }

        try {
            DB::beginTransaction();
            foreach ($invoice->items as $item) {
                Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
            }
            $invoice->items()->delete();
            $invoice->delete();
            DB::commit();

            return response()->noContent();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("PurchaseInvoice destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression.',
            ], 500);
        }
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $lastInvoice = PurchaseInvoice::where('user_id', auth()->id())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'FA-' . $year . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}