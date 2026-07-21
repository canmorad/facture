<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashRegisterService
{
    protected function getCompanyId(): int
    {
        return config('app.current_company_id')
            ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }

    public function getAll(): array
    {
        $companyId = $this->getCompanyId();

        $cashRegisters = CashRegister::where('company_id', $companyId)
            ->active()
            ->with(['activeSession', 'company'])
            ->get()
            ->map(function ($register) {
                $register->loadMissing('activeSession.openedBy');
                $register->calculated_balance = $register->calculateActualBalance();
                return $register;
            });

        return [
            'cash_registers' => $cashRegisters,
        ];
    }

    public function getPaginated(array $filters = [], int $perPage = 10): array
    {
        $companyId = $this->getCompanyId();

        $query = CashRegister::where('company_id', $companyId)
            ->with(['activeSession']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === 'true');
        }

        $cashRegisters = $query->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate($perPage);

        $cashRegisters->getCollection()->transform(function ($register) {
            $register->calculated_balance = $register->calculateActualBalance();
            return $register;
        });

        return [
            'data' => $cashRegisters->items(),
            'total' => $cashRegisters->total(),
            'per_page' => $cashRegisters->perPage(),
            'current_page' => $cashRegisters->currentPage(),
            'last_page' => $cashRegisters->lastPage(),
            'from' => $cashRegisters->firstItem(),
            'to' => $cashRegisters->lastItem(),
        ];
    }

    public function findById(int $id): CashRegister
    {
        $companyId = $this->getCompanyId();

        return CashRegister::where('company_id', $companyId)
            ->with(['activeSession', 'sessions' => function ($query) {
                $query->latest('opened_at')->limit(10);
            }])
            ->findOrFail($id);
    }

    public function getDashboardData(int $cashRegisterId): array
    {
        $companyId = $this->getCompanyId();

        $register = CashRegister::where('company_id', $companyId)
            ->findOrFail($cashRegisterId);

        $activeSession = $register->activeSession()->first();

        $todayTransactions = collect();
        $todayTotalIn = 0;
        $todayTotalOut = 0;

        if ($activeSession) {
            $todayTransactions = CashTransaction::where('session_id', $activeSession->id)
                ->with(['user', 'transactionable'])
                ->orderBy('transaction_date', 'desc')
                ->get();

            $todayTotalIn = $todayTransactions->where('type', 'in')->sum('amount');
            $todayTotalOut = $todayTransactions->where('type', 'out')->sum('amount');
        }

        $recentSessions = CashRegisterSession::where('cash_register_id', $cashRegisterId)
            ->where('status', 'closed')
            ->with(['openedBy', 'closedBy'])
            ->latest('opened_at')
            ->limit(5)
            ->get();

        return [
            'register' => $register,
            'active_session' => $activeSession,
            'today_transactions' => $todayTransactions,
            'today_total_in' => $todayTotalIn,
            'today_total_out' => $todayTotalOut,
            'expected_balance' => $activeSession ? $activeSession->calculateExpectedClosingBalance() : 0,
            'recent_sessions' => $recentSessions,
        ];
    }

    public function create(array $data): CashRegister
    {
        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $data['company_id'] = $companyId;
            $data['current_balance'] = $data['opening_balance'] ?? 0;

            if (empty($data['code'])) {
                $count = CashRegister::where('company_id', $companyId)->count() + 1;
                $data['code'] = 'CAISSE-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }

            if (!empty($data['is_default']) && $data['is_default']) {
                CashRegister::where('company_id', $companyId)
                    ->update(['is_default' => false]);
            }

            $cashRegister = CashRegister::create($data);

            DB::commit();

            return $cashRegister->load('company');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService create error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function update(int $id, array $data): CashRegister
    {
        $companyId = $this->getCompanyId();

        $cashRegister = CashRegister::where('company_id', $companyId)
            ->findOrFail($id);

        DB::beginTransaction();

        try {
            if (!empty($data['is_default']) && $data['is_default']) {
                CashRegister::where('company_id', $companyId)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $cashRegister->update($data);

            DB::commit();

            return $cashRegister->fresh('company');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService update error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $companyId = $this->getCompanyId();

        $cashRegister = CashRegister::where('company_id', $companyId)
            ->findOrFail($id);

        $hasOpenSession = $cashRegister->activeSession()->exists();

        if ($hasOpenSession) {
            throw new \RuntimeException('Impossible de supprimer une caisse avec une session ouverte.');
        }

        $cashRegister->delete();
    }

    public function openSession(int $cashRegisterId, array $data): CashRegisterSession
    {
        $companyId = $this->getCompanyId();
        $userId = auth()->id();

        $cashRegister = CashRegister::where('company_id', $companyId)
            ->findOrFail($cashRegisterId);

        $existingOpenSession = $cashRegister->activeSession()->first();

        if ($existingOpenSession) {
            throw new \RuntimeException('Une session est déjà ouverte pour cette caisse.');
        }

        DB::beginTransaction();

        try {
            $openingBalance = $data['opening_balance'] ?? $cashRegister->current_balance;

            $session = CashRegisterSession::create([
                'company_id' => $companyId,
                'cash_register_id' => $cashRegisterId,
                'opened_by_user_id' => $userId,
                'opening_balance' => $openingBalance,
                'expected_closing_balance' => $openingBalance,
                'status' => 'open',
                'opened_at' => now(),
                'opening_notes' => $data['notes'] ?? null,
            ]);

            $cashRegister->current_balance = $openingBalance;
            $cashRegister->save();

            DB::commit();

            return $session->load(['cashRegister', 'openedBy']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService openSession error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function closeSession(int $sessionId, array $data): CashRegisterSession
    {
        $companyId = $this->getCompanyId();
        $userId = auth()->id();

        $session = CashRegisterSession::where('company_id', $companyId)
            ->with('cashRegister')
            ->findOrFail($sessionId);

        if ($session->status !== 'open') {
            throw new \RuntimeException('Cette session est déjà clôturée.');
        }

        DB::beginTransaction();

        try {
            $actualBalance = $data['actual_closing_balance'];

            $expectedBalance = $session->calculateExpectedClosingBalance();
            $discrepancy = $actualBalance - $expectedBalance;

            $session->update([
                'closed_by_user_id' => $userId,
                'actual_closing_balance' => $actualBalance,
                'expected_closing_balance' => $expectedBalance,
                'discrepancy' => $discrepancy,
                'status' => 'closed',
                'closed_at' => now(),
                'closing_notes' => $data['notes'] ?? null,
            ]);

            $session->cashRegister->update([
                'current_balance' => $actualBalance,
            ]);

            DB::commit();

            return $session->fresh(['cashRegister', 'openedBy', 'closedBy']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService closeSession error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function getTransactions(array $filters = [], int $perPage = 15): array
    {
        $companyId = $this->getCompanyId();

        $query = CashTransaction::where('company_id', $companyId)
            ->with(['cashRegister', 'session', 'user', 'transactionable']);

        if (!empty($filters['cash_register_id'])) {
            $query->where('cash_register_id', $filters['cash_register_id']);
        }

        if (!empty($filters['session_id'])) {
            $query->where('session_id', $filters['session_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('transaction_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('transaction_date', '<=', $filters['date_to']);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return [
            'data' => $transactions->items(),
            'total' => $transactions->total(),
            'per_page' => $transactions->perPage(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'from' => $transactions->firstItem(),
            'to' => $transactions->lastItem(),
        ];
    }

    public function createTransaction(array $data): CashTransaction
    {
        $companyId = $this->getCompanyId();
        $userId = auth()->id();

        if ($data['type'] === 'transfer') {
            return $this->createTransferTransaction($data, $companyId, $userId);
        }

        DB::beginTransaction();

        try {
            $session = CashRegisterSession::where('company_id', $companyId)
                ->where('cash_register_id', $data['cash_register_id'])
                ->open()
                ->firstOrFail();

            $transaction = CashTransaction::create([
                'company_id' => $companyId,
                'cash_register_id' => $data['cash_register_id'],
                'session_id' => $session->id,
                'user_id' => $userId,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'transactionable_type' => $data['transactionable_type'] ?? null,
                'transactionable_id' => $data['transactionable_id'] ?? null,
                'is_verified' => $data['is_verified'] ?? false,
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);

            $session->expected_closing_balance = $session->calculateExpectedClosingBalance();
            $session->save();

            $cashRegister = $session->cashRegister;
            if ($data['type'] === 'in') {
                $cashRegister->current_balance += $data['amount'];
            } elseif ($data['type'] === 'out') {
                $cashRegister->current_balance -= $data['amount'];
            }
            $cashRegister->save();

            DB::commit();

            return $transaction->load(['cashRegister', 'session', 'user']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService createTransaction error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    protected function createTransferTransaction(array $data, int $companyId, int $userId): CashTransaction
    {
        if (empty($data['from_cash_register_id']) || empty($data['to_cash_register_id'])) {
            throw new \RuntimeException('Les caisses source et destination sont requises pour un transfert.');
        }

        DB::beginTransaction();

        try {
            $fromSession = CashRegisterSession::where('company_id', $companyId)
                ->where('cash_register_id', $data['from_cash_register_id'])
                ->open()
                ->firstOrFail();

            $toSession = CashRegisterSession::where('company_id', $companyId)
                ->where('cash_register_id', $data['to_cash_register_id'])
                ->open()
                ->first();

            if (!$toSession) {
                throw new \RuntimeException('La caisse de destination doit avoir une session ouverte.');
            }

            $transaction = CashTransaction::create([
                'company_id' => $companyId,
                'cash_register_id' => $data['from_cash_register_id'],
                'session_id' => $fromSession->id,
                'user_id' => $userId,
                'type' => 'transfer',
                'amount' => $data['amount'],
                'payment_method' => 'transfer',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'from_cash_register_id' => $data['from_cash_register_id'],
                'to_cash_register_id' => $data['to_cash_register_id'],
                'is_verified' => true,
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);

            $fromCashRegister = $fromSession->cashRegister;
            $fromCashRegister->current_balance -= $data['amount'];
            $fromCashRegister->save();

            $toCashRegister = $toSession->cashRegister;
            $toCashRegister->current_balance += $data['amount'];
            $toCashRegister->save();

            $fromSession->expected_closing_balance = $fromSession->calculateExpectedClosingBalance();
            $fromSession->save();

            $toSession->expected_closing_balance = $toSession->calculateExpectedClosingBalance();
            $toSession->save();

            DB::commit();

            return $transaction->load(['cashRegister', 'fromCashRegister', 'toCashRegister', 'session', 'user']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService createTransferTransaction error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function updateTransaction(int $transactionId, array $data): CashTransaction
    {
        $companyId = $this->getCompanyId();
        $userId = auth()->id();

        $transaction = CashTransaction::where('company_id', $companyId)
            ->with(['session', 'cashRegister', 'fromCashRegister', 'toCashRegister'])
            ->findOrFail($transactionId);

        if ($transaction->is_verified) {
            throw new \RuntimeException('Impossible de modifier une transaction vérifiée.');
        }

        if ($transaction->type === 'transfer') {
            throw new \RuntimeException('Impossible de modifier un transfert. Supprimez-le et recréez-le.');
        }

        DB::beginTransaction();

        try {
            $oldAmount = $transaction->amount;
            $oldType = $transaction->type;
            $newAmount = $data['amount'];
            $newType = $data['type'] ?? $transaction->type;

            $session = $transaction->session;
            $cashRegister = $transaction->cashRegister;

            $cashRegister->current_balance -= $oldType === 'in' ? $oldAmount : -$oldAmount;

            $transaction->update([
                'type' => $newType,
                'amount' => $newAmount,
                'payment_method' => $data['payment_method'] ?? $transaction->payment_method,
                'reference' => $data['reference'] ?? $transaction->reference,
                'description' => $data['description'],
                'transactionable_type' => $data['transactionable_type'] ?? $transaction->transactionable_type,
                'transactionable_id' => $data['transactionable_id'] ?? $transaction->transactionable_id,
                'transaction_date' => $data['transaction_date'] ?? $transaction->transaction_date,
            ]);

            $cashRegister->current_balance += $newType === 'in' ? $newAmount : -$newAmount;
            $cashRegister->save();

            if ($session) {
                $session->expected_closing_balance = $session->calculateExpectedClosingBalance();
                $session->save();
            }

            DB::commit();

            return $transaction->fresh(['cashRegister', 'session', 'user']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService updateTransaction error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function deleteTransaction(int $transactionId): void
    {
        $companyId = $this->getCompanyId();

        $transaction = CashTransaction::where('company_id', $companyId)
            ->with(['session', 'cashRegister', 'fromCashRegister', 'toCashRegister'])
            ->findOrFail($transactionId);

        if ($transaction->is_verified) {
            throw new \RuntimeException('Impossible de supprimer une transaction vérifiée.');
        }

        DB::beginTransaction();

        try {
            if ($transaction->type === 'transfer') {
                $fromCashRegister = $transaction->fromCashRegister;
                $toCashRegister = $transaction->toCashRegister;

                $fromCashRegister->current_balance += $transaction->amount;
                $fromCashRegister->save();

                $toCashRegister->current_balance -= $transaction->amount;
                $toCashRegister->save();

                $fromSession = CashRegisterSession::where('cash_register_id', $fromCashRegister->id)->open()->first();
                $toSession = CashRegisterSession::where('cash_register_id', $toCashRegister->id)->open()->first();

                if ($fromSession) {
                    $fromSession->expected_closing_balance = $fromSession->calculateExpectedClosingBalance();
                    $fromSession->save();
                }

                if ($toSession) {
                    $toSession->expected_closing_balance = $toSession->calculateExpectedClosingBalance();
                    $toSession->save();
                }
            } else {
                $session = $transaction->session;
                $cashRegister = $transaction->cashRegister;

                if ($transaction->type === 'in') {
                    $cashRegister->current_balance -= $transaction->amount;
                } elseif ($transaction->type === 'out') {
                    $cashRegister->current_balance += $transaction->amount;
                }

                $cashRegister->save();

                if ($session) {
                    $session->expected_closing_balance = $session->calculateExpectedClosingBalance();
                    $session->save();
                }
            }

            $transaction->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CashRegisterService deleteTransaction error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function getCreationData(): array
    {
        $companyId = $this->getCompanyId();

        $cashRegisters = CashRegister::where('company_id', $companyId)
            ->active()
            ->get();

        $activeSessions = CashRegisterSession::where('company_id', $companyId)
            ->open()
            ->with('cashRegister')
            ->get()
            ->keyBy('cash_register_id');

        return [
            'cash_registers' => $cashRegisters,
            'active_sessions' => $activeSessions,
        ];
    }

    public function toggleStatus(int $id): CashRegister
    {
        $companyId = $this->getCompanyId();

        $cashRegister = CashRegister::where('company_id', $companyId)
            ->findOrFail($id);

        $cashRegister->is_active = !$cashRegister->is_active;
        $cashRegister->save();

        return $cashRegister;
    }

    public function setDefault(int $id): CashRegister
    {
        $companyId = $this->getCompanyId();

        CashRegister::where('company_id', $companyId)
            ->update(['is_default' => false]);

        $cashRegister = CashRegister::where('company_id', $companyId)
            ->findOrFail($id);

        $cashRegister->is_default = true;
        $cashRegister->save();

        return $cashRegister;
    }
}
