<?php

namespace App\Console\Commands;

use App\Services\CsvExport\CsvExportTaskInfluxSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportCsvExportsToInfluxCommand extends Command
{
    protected $signature = 'csv-export:import-influx {--limit=50 : Maximum tasks to process per run}';

    protected $description = 'Import CSV export rows to InfluxDB periodically';

    public function handle(CsvExportTaskInfluxSyncService $csvExportTaskInfluxSyncService): int
    {
        $limit = (int) $this->option('limit');
        $report = $csvExportTaskInfluxSyncService->importPendingTasksReport($limit);
        $importedRows = (int) $report['imported_rows'];

        $this->line('Tasks selected: '.$report['tasks_selected'].' / processed: '.$report['tasks_processed']);
        $this->line('Tasks imported: '.$report['tasks_imported'].' / skipped: '.$report['tasks_skipped']);

        if (! empty($report['skip_reasons'])) {
            $reasonPairs = [];
            foreach ($report['skip_reasons'] as $reason => $count) {
                $reasonPairs[] = $reason.'='.$count;
            }

            $this->line('Skip reasons: '.implode(', ', $reasonPairs));
        }

        foreach ($report['error_samples'] as $sample) {
            $this->warn(sprintf(
                'Task #%d %s: %s',
                $sample['task_id'],
                $sample['reason'],
                $sample['detail']
            ));
        }

        $transportFailed = (($report['skip_reasons']['service_unreachable'] ?? 0) > 0)
            || (($report['skip_reasons']['http_failed_response'] ?? 0) > 0);

        if ($transportFailed) {
            $this->error('Influx service did not respond correctly. Please check InfluxDB container/network and database token/database settings.');
            Log::error('Influx import command finished with transport failures.', $report);

            return self::FAILURE;
        }

        Log::info('Influx import command summary.', $report);

        $this->info('Influx import finished. Imported rows: '.$importedRows);

        return self::SUCCESS;
    }
}
