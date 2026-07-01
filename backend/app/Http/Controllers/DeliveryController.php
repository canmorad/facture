<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function index()
    {
        $deliveries = Delivery::with(['client', 'items.product'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($deliveries);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'date'            => 'required|date',
            'transporteur'    => 'required|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'status'          => 'nullable|in:draft,pending,shipped,delivered,cancelled',
            'items'           => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            $number = $this->generateNumber();
            $status = $request->status ?? 'draft';

            $delivery = Delivery::create([
                'user_id'         => auth()->id(),
                'client_id'       => $request->client_id,
                'number'          => $number,
                'date'            => $request->date,
                'transporteur'    => $request->transporteur,
                'tracking_number' => $request->tracking_number,
                'status'          => $status,
            ]);

            foreach ($request->items as $item) {
                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                ]);

                // Décrémentation du stock si le bon est expédié ou livré dès la création
                if (in_array($status, ['shipped', 'delivered'])) {
                    Product::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
                }
            }

            DB::commit();

            return response()->json($delivery->load(['client', 'items.product']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création du bon de livraison.'], 500);
        }
    }

    public function show($id)
    {
        $delivery = Delivery::with(['client', 'items.product'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($delivery);
    }

    public function update(Request $request, $id)
    {
        $delivery = Delivery::where('user_id', auth()->id())->findOrFail($id);
        $oldStatus = $delivery->status;

        $request->validate([
            'client_id'       => 'sometimes|exists:clients,id',
            'date'            => 'sometimes|date',
            'transporteur'    => 'sometimes|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
            'status'          => 'sometimes|in:draft,pending,shipped,delivered,cancelled',
            'items'           => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity'   => 'required_with:items|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            // Mise à jour des champs simples
            $delivery->update($request->only([
                'client_id', 'date', 'transporteur', 'tracking_number', 'status'
            ]));

            // Gestion des articles si fournis
            if ($request->has('items')) {
                // Restaurer l'ancien stock avant suppression des anciennes lignes
                foreach ($delivery->items as $oldItem) {
                    if (in_array($oldStatus, ['shipped', 'delivered'])) {
                        Product::where('id', $oldItem->product_id)->increment('stock', $oldItem->quantity);
                    }
                }
                $delivery->items()->delete();

                foreach ($request->items as $item) {
                    DeliveryItem::create([
                        'delivery_id' => $delivery->id,
                        'product_id'  => $item['product_id'],
                        'quantity'    => $item['quantity'],
                    ]);
                    // Appliquer le nouveau stock selon le statut actuel
                    if (in_array($delivery->status, ['shipped', 'delivered'])) {
                        Product::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
                    }
                }
            } else {
                // Gestion du changement de statut sans modification des articles
                if ($oldStatus !== $delivery->status) {
                    foreach ($delivery->items as $item) {
                        // Si on passe à "shipped" ou "delivered" : sortie de stock
                        if (in_array($delivery->status, ['shipped', 'delivered']) && !in_array($oldStatus, ['shipped', 'delivered'])) {
                            Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                        }
                        // Si on passe à "cancelled" : retour en stock
                        elseif ($delivery->status === 'cancelled' && $oldStatus !== 'cancelled') {
                            Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                        }
                        // Si on sort de "cancelled" vers "shipped/delivered" (rare)
                        elseif ($oldStatus === 'cancelled' && in_array($delivery->status, ['shipped', 'delivered'])) {
                            Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json($delivery->load(['client', 'items.product']), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la mise à jour.'], 500);
        }
    }

    public function destroy($id)
    {
        $delivery = Delivery::where('user_id', auth()->id())->findOrFail($id);

        try {
            DB::beginTransaction();
            // Restaurer le stock si le bon avait déjà impacté le stock
            if (in_array($delivery->status, ['shipped', 'delivered'])) {
                foreach ($delivery->items as $item) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }
            $delivery->items()->delete();
            $delivery->delete();
            DB::commit();

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression.'], 500);
        }
    }

    private function generateNumber(): string
    {
        $year = now()->format('Y');
        $last = Delivery::where('user_id', auth()->id())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            $lastNum = intval(substr($last->number, -4));
            $newNum = $lastNum + 1;
        } else {
            $newNum = 1;
        }

        return 'BL-' . $year . '-' . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }
}