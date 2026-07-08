<?php

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

class ProcessRecurringInvoices extends Command
{
    protected $signature = 'recurring:process';

    protected $description = 'Génère les factures pour tous les modèles récurrents actifs dont la date d\'exécution est arrivée à échéance.';

    public function handle(RecurringInvoiceService $service): int
    {
        $this->info('Démarrage du traitement des factures récurrentes...');

        $generated = $service->processPendingRecurrences();

        if (empty($generated)) {
            $this->info('Aucune facture récurrente à générer.');

            return Command::SUCCESS;
        }

        foreach ($generated as $entry) {
            $this->line(sprintf(
                'Facture générée : #%d (Document #%d) depuis le modèle récurrent #%d',
                $entry['number'],
                $entry['document_id'],
                $entry['recurring_id']
            ));
        }

        $this->info(sprintf('%d facture(s) générée(s) avec succès.', count($generated)));

        return Command::SUCCESS;
    }
}