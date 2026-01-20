<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Radius\Radacct;
use App\Models\Tenants\TenantTrafficAnalytics;
use App\Models\Tenants\NetworkUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AggregateTrafficData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'analytics:aggregate-traffic {--hours=1 : Number of hours to aggregate}';

    /**
     * The console command description.
     */
    protected $description = 'Aggregate Radacct data into hourly traffic analytics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $this->info("Aggregating traffic data for the last {$hours} hour(s)...");

        $startTime = Carbon::now()->subHours($hours);
        $endTime = Carbon::now();

        // Get all sessions that started or were active in this period
        $sessions = Radacct::whereBetween('acctstarttime', [$startTime, $endTime])
            ->orWhere(function ($query) use ($startTime, $endTime) {
                $query->where('acctstarttime', '<', $startTime)
                      ->where(function ($q) use ($endTime) {
                          $q->whereNull('acctstoptime')
                            ->orWhere('acctstoptime', '>', $endTime);
                      });
            })
            ->get();

        $this->info("Found {$sessions->count()} sessions to process");

        $aggregated = [];
        $bar = $this->output->createProgressBar($sessions->count());

        foreach ($sessions as $session) {
            // Get user and tenant info
            $user = NetworkUser::where('username', $session->username)->first();
            
            if (!$user || !$user->tenant_id) {
                $bar->advance();
                continue;
            }

            $tenantId = $user->tenant_id;
            $userId = $user->id;

            // Determine the hour bucket
            $sessionStart = Carbon::parse($session->acctstarttime);
            $sessionEnd = $session->acctstoptime ? Carbon::parse($session->acctstoptime) : Carbon::now();

            // Aggregate by hour
            $currentHour = $sessionStart->copy()->startOfHour();
            
            while ($currentHour <= $sessionEnd) {
                $date = $currentHour->toDateString();
                $hour = $currentHour->hour;
                
                $key = "{$tenantId}_{$userId}_{$date}_{$hour}";

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'date' => $date,
                        'hour' => $hour,
                        'bytes_in' => 0,
                        'bytes_out' => 0,
                        'total_bytes' => 0,
                        'protocol' => null,
                    ];
                }

                // Add bytes (simplified - assumes even distribution across hours)
                $aggregated[$key]['bytes_in'] += $session->acctinputoctets ?? 0;
                $aggregated[$key]['bytes_out'] += $session->acctoutputoctets ?? 0;
                $aggregated[$key]['total_bytes'] += ($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0);

                $currentHour->addHour();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Insert or update aggregated data
        $this->info("Inserting aggregated data...");
        
        foreach ($aggregated as $data) {
            $existing = TenantTrafficAnalytics::where([
                'tenant_id' => $data['tenant_id'],
                'user_id' => $data['user_id'],
                'date' => $data['date'],
                'hour' => $data['hour'],
            ])->first();

            if ($existing) {
                // Update existing record by incrementing
                $existing->increment('bytes_in', $data['bytes_in']);
                $existing->increment('bytes_out', $data['bytes_out']);
                $existing->increment('total_bytes', $data['total_bytes']);
            } else {
                // Create new record
                TenantTrafficAnalytics::create([
                    'tenant_id' => $data['tenant_id'],
                    'user_id' => $data['user_id'],
                    'date' => $data['date'],
                    'hour' => $data['hour'],
                    'bytes_in' => $data['bytes_in'],
                    'bytes_out' => $data['bytes_out'],
                    'total_bytes' => $data['total_bytes'],
                    'protocol' => $data['protocol'],
                ]);
            }
        }

        $this->info("✓ Aggregation complete! Processed " . count($aggregated) . " hourly records.");
        
        return Command::SUCCESS;
    }
}
