<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    use \App\Traits\MediaUpload;

    public function getAll()
    {
        $companyId = $this->getCompanyId();

        $expenses = Expense::where('company_id', $companyId)
            ->with(['supplier', 'media'])
            ->orderBy('created_at', 'desc')
            ->get();

        $expenses->transform(function ($expense) {
            if ($expense->media) {
                $expense->media->transform(function ($media) {
                    $media->url = $media->file_path ? Storage::url($media->file_path) : null;
                    return $media;
                });
            }
            return $expense;
        });

        return $expenses;
    }

    public function create(array $validated, array $files = []): Expense
    {
        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $validated['company_id'] = $companyId;

            if (empty($validated['supplier_id'])) {
                unset($validated['supplier_id']);
            }

            $expense = Expense::create($validated);

            foreach ($files as $file) {
                $expense->attachMedia($file, 'expenses');
            }

            DB::commit();

            $expense->load(['supplier', 'media']);

            if ($expense->media) {
                $expense->media->transform(function ($media) {
                    $media->url = $media->file_path ? Storage::url($media->file_path) : null;
                    return $media;
                });
            }

            return $expense;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ExpenseService create error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function update(int $id, array $validated, array $files = []): Expense
    {
        $companyId = $this->getCompanyId();

        $expense = Expense::where('company_id', $companyId)->findOrFail($id);

        DB::beginTransaction();

        try {
            if (empty($validated['supplier_id'])) {
                unset($validated['supplier_id']);
            }

            $expense->update($validated);

            foreach ($files as $file) {
                $expense->attachMedia($file, 'expenses');
            }

            DB::commit();

            $expense->load(['supplier', 'media']);

            if ($expense->media) {
                $expense->media->transform(function ($media) {
                    $media->url = $media->file_path ? Storage::url($media->file_path) : null;
                    return $media;
                });
            }

            return $expense;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ExpenseService update error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function destroy(int $id): void
    {
        $companyId = $this->getCompanyId();

        $expense = Expense::where('company_id', $companyId)->findOrFail($id);

        foreach ($expense->media as $media) {
            $this->deleteFile($media->file_path);
            $media->delete();
        }

        $expense->delete();
    }

    public function getCreationData(): array
    {
        $companyId = $this->getCompanyId();

        $suppliers = Supplier::where('company_id', $companyId)->orderBy('name')->get();

        return [
            'suppliers' => $suppliers,
        ];
    }

    public function getSuppliers(): array
    {
        $companyId = $this->getCompanyId();

        return Supplier::where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function createSupplier(array $data): Supplier
    {
        $companyId = $this->getCompanyId();

        return Supplier::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'ice' => $data['ice'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }

    public function updateSupplier(int $id, array $data): Supplier
    {
        $companyId = $this->getCompanyId();

        $supplier = Supplier::where('company_id', $companyId)->findOrFail($id);

        $supplier->update($data);

        return $supplier;
    }

    public function toggleStatus(int $id): Expense
    {
        $companyId = $this->getCompanyId();

        $expense = Expense::where('company_id', $companyId)->findOrFail($id);

        $expense->status = $expense->status === 'paid' ? 'unpaid' : 'paid';
        $expense->save();

        return $expense;
    }

    public function destroySupplier(int $id): void
    {
        $companyId = $this->getCompanyId();

        Supplier::where('company_id', $companyId)->findOrFail($id)->delete();
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id') ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }
}