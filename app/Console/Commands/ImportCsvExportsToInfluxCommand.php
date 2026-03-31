<?php

namespace App\Console\Commands;

use App\Services\CsvExport\CsvExportTaskInfluxSyncService;
use Illuminate\Console\Command;

class ImportCsvExportsToInfluxCommand extends Command
{
    protected $signature = 'csv-export:import-influx {--limit=50 : Maximum tasks to process per run}';

    protected $description = 'Import CSV export rows to InfluxDB periodically';

    public function handle(CsvExportTaskInfluxSyncService $csvExportTaskInfluxSyncService): int
    {
        $limit = (int) $this->option('limit');
        $importedRows = $csvExportTaskInfluxSyncService->importPendingTasks($limit);

        $this->info('Influx import finished. Imported rows: '.$importedRows);

        return self::SUCCESS;
    }
}
