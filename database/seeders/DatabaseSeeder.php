<?php

namespace Database\Seeders;

use App\Models\AiPrediction;
use App\Models\AuditLog;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskHistory;
use App\Models\Message;
use App\Models\PowerReading;
use App\Models\Problem;
use App\Models\Report;
use App\Models\ReportAiSummary;
use App\Models\SensorReading;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\Solution;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const TIMEZONE = 'Africa/Tunis';

    public function run(): void
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $twoWeeksAgo = $now->subDays(14);

        // 1. Core Users (Department Head + IT Staff / Technicians)
        $departmentHead = User::query()->updateOrCreate(
            ['email' => 'head@infraguard.tn'],
            [
                'name' => 'Baha Eddine Ammar',
                'department' => 'Infrastructure & IT Operations',
                'phone_number' => '+216 20 123 456',
                'role' => 'department_head',
                'status' => 'approved',
                'is_approved' => true,
                'email_verified_at' => $twoWeeksAgo->subDays(2),
                'password' => Hash::make('password'),
            ]
        );

        $karim = User::query()->updateOrCreate(
            ['email' => 'karim.bensalah@infraguard.tn'],
            [
                'name' => 'Karim Ben Salah',
                'department' => 'Network Operations',
                'phone_number' => '+216 98 765 432',
                'role' => 'it_staff',
                'status' => 'approved',
                'is_approved' => true,
                'email_verified_at' => $twoWeeksAgo->subDays(2),
                'password' => Hash::make('password'),
            ]
        );

        $sarra = User::query()->updateOrCreate(
            ['email' => 'sarra.mansour@infraguard.tn'],
            [
                'name' => 'Sarra Mansour',
                'department' => 'Systems & Cloud Engineering',
                'phone_number' => '+216 55 432 109',
                'role' => 'staff',
                'status' => 'approved',
                'is_approved' => true,
                'email_verified_at' => $twoWeeksAgo->subDays(2),
                'password' => Hash::make('password'),
            ]
        );

        $youssef = User::query()->updateOrCreate(
            ['email' => 'youssef.trabelsi@infraguard.tn'],
            [
                'name' => 'Youssef Trabelsi',
                'department' => 'Hardware Maintenance',
                'phone_number' => '+216 22 876 543',
                'role' => 'it_staff',
                'status' => 'approved',
                'is_approved' => true,
                'email_verified_at' => $twoWeeksAgo->subDays(2),
                'password' => Hash::make('password'),
            ]
        );

        $previewUser = User::query()->updateOrCreate(
            ['email' => 'dashboard-preview@infraguard.invalid'],
            [
                'name' => 'Local Preview',
                'department' => 'Systems',
                'role' => 'staff',
                'status' => 'approved',
                'is_approved' => true,
                'email_verified_at' => $twoWeeksAgo->subDays(1),
                'password' => Hash::make('password'),
            ]
        );

        // 2. Monitored Servers
        $localPc = Server::query()->firstOrCreate(
            ['identifier' => 'local-pc'],
            [
                'name' => 'Local PC',
                'server_type' => 'Local PC',
                'ip_address' => '127.0.0.1',
                'description' => 'Default monitored workstation and telemetry ingestion agent.',
                'api_token' => Str::random(40),
                'last_seen_at' => $now,
            ]
        );
        $localPc->update(['last_seen_at' => $now]);

        $dbServer = Server::query()->firstOrCreate(
            ['identifier' => 'srv-db-01'],
            [
                'name' => 'Database Cluster (Primary)',
                'server_type' => 'Database Server',
                'ip_address' => '192.168.1.10',
                'description' => 'Primary MySQL 8.4 database cluster node with live replication.',
                'api_token' => Str::random(40),
                'last_seen_at' => $now->subSeconds(45),
            ]
        );

        $webServer = Server::query()->firstOrCreate(
            ['identifier' => 'srv-app-01'],
            [
                'name' => 'Web Application Node 01',
                'server_type' => 'Web Server',
                'ip_address' => '192.168.1.20',
                'description' => 'Production web gateway running reverse proxy and application workers.',
                'api_token' => Str::random(40),
                'last_seen_at' => $now->subSeconds(30),
            ]
        );

        $queueServer = Server::query()->firstOrCreate(
            ['identifier' => 'srv-queue-01'],
            [
                'name' => 'Async Worker & Queue Node',
                'server_type' => 'Worker Node',
                'ip_address' => '192.168.1.21',
                'description' => 'High-throughput background worker handling telemetry ingestion and alerts.',
                'api_token' => Str::random(40),
                'last_seen_at' => $now->subSeconds(15),
            ]
        );

        $storageServer = Server::query()->firstOrCreate(
            ['identifier' => 'srv-storage-san'],
            [
                'name' => 'SAN Storage Appliance',
                'server_type' => 'Storage Array',
                'ip_address' => '192.168.1.50',
                'description' => 'ZFS dual-controller 48TB SAN storage for backup pools and metric snapshots.',
                'api_token' => Str::random(40),
                'last_seen_at' => $now->subMinutes(1),
            ]
        );

        $edgeGw = Server::query()->firstOrCreate(
            ['identifier' => 'srv-edge-gw'],
            [
                'name' => 'Edge Firewall & VPN Gateway',
                'server_type' => 'Network Gateway',
                'ip_address' => '192.168.1.1',
                'description' => 'Perimeter security appliance, WireGuard tunnel, and traffic shaping.',
                'api_token' => Str::random(40),
                'last_seen_at' => $now->subSeconds(20),
            ]
        );

        $servers = [$localPc, $dbServer, $webServer, $queueServer, $storageServer, $edgeGw];

        // 3. Environmental Sensor Readings (14 Days, every 15 minutes = 1,344 points)
        // Check if we already have sufficient readings to avoid duplicate bloat if re-run
        if (SensorReading::query()->where('recorded_at', '>=', $twoWeeksAgo)->count() < 100) {
            $sensorRecords = [];
            $totalIntervals = (14 * 24 * 4); // 1,344 fifteen-minute steps
            $start = $twoWeeksAgo;

            for ($i = 0; $i <= $totalIntervals; $i++) {
                $sampleTime = $start->addMinutes(15 * $i);
                $hour = (int) $sampleTime->format('G');
                $dayIndex = (int) floor($i / (24 * 4));

                // Diurnal wave: cooler at night (04:00), slightly warmer during active afternoon (14:00)
                $diurnal = sin((($hour - 8) / 24) * 2 * M_PI) * 0.7;
                $randomJitter = ((($i * 17) % 19) - 9) * 0.05;

                // Transient temperature bump 6 days ago (Day 8: simulated maintenance / UPS test event)
                $eventBump = ($dayIndex === 8 && $hour >= 10 && $hour <= 14) ? 2.3 : 0.0;

                $temperature = round(22.2 + $diurnal + $randomJitter + $eventBump, 1);
                $humidity = round(46.8 - ($diurnal * 1.5) + (((($i * 23) % 21) - 10) * 0.08), 1);

                $sensorRecords[] = [
                    'device_id' => 'dht22-rack-01',
                    'temperature' => max(19.5, min(29.0, $temperature)),
                    'humidity' => max(38.0, min(58.0, $humidity)),
                    'recorded_at' => $sampleTime->toDateTimeString(),
                    'created_at' => $sampleTime->toDateTimeString(),
                    'updated_at' => $sampleTime->toDateTimeString(),
                ];

                if (count($sensorRecords) >= 200) {
                    SensorReading::query()->insert($sensorRecords);
                    $sensorRecords = [];
                }
            }
            if (! empty($sensorRecords)) {
                SensorReading::query()->insert($sensorRecords);
            }
        }

        // 4. Power Readings (14 Days, synchronized with sensor samples)
        if (PowerReading::query()->where('created_at', '>=', $twoWeeksAgo)->count() < 100) {
            $powerRecords = [];
            $totalIntervals = (14 * 24 * 4);
            $cumulativeKwh = 4185.0;

            for ($i = 0; $i <= $totalIntervals; $i++) {
                $sampleTime = $twoWeeksAgo->addMinutes(15 * $i);
                $hour = (int) $sampleTime->format('G');
                $dayIndex = (int) floor($i / (24 * 4));

                // Business hours (08:00 to 18:00) draw slightly more power
                $loadFactor = ($hour >= 8 && $hour <= 18) ? 1.25 : 0.95;
                $eventLoad = ($dayIndex === 8 && $hour >= 10 && $hour <= 14) ? 420.0 : 0.0;

                $voltage = round(230.8 + (sin($i * 0.1) * 1.4) + (((($i * 13) % 11) - 5) * 0.12), 1);
                $powerW = round((1050.0 * $loadFactor) + $eventLoad + (((($i * 31) % 43) - 21) * 2.8), 1);
                $currentA = round($powerW / ($voltage * 0.96), 2);
                $cumulativeKwh = round($cumulativeKwh + ($powerW * (15 / 60) / 1000), 2);

                $powerRecords[] = [
                    'device_id' => 'pzem-rack-main',
                    'voltage_v' => $voltage,
                    'current_a' => $currentA,
                    'power_w' => $powerW,
                    'energy_kwh' => $cumulativeKwh,
                    'frequency_hz' => 50.01,
                    'power_factor' => 0.96,
                    'created_at' => $sampleTime->toDateTimeString(),
                    'updated_at' => $sampleTime->toDateTimeString(),
                ];

                if (count($powerRecords) >= 200) {
                    PowerReading::query()->insert($powerRecords);
                    $powerRecords = [];
                }
            }
            if (! empty($powerRecords)) {
                PowerReading::query()->insert($powerRecords);
            }
        }

        // 5. Server Metrics across 14 Days
        if (ServerMetric::query()->where('created_at', '>=', $twoWeeksAgo)->count() < 100) {
            $metricRecords = [];
            // Sample every 2 hours over 14 days = 168 snapshots per server
            for ($step = 0; $step <= (14 * 12); $step++) {
                $time = $twoWeeksAgo->addHours(2 * $step);
                $uptime = 120000 + ($step * 7200);

                foreach ($servers as $srv) {
                    $baseCpu = match ($srv->identifier) {
                        'srv-db-01' => 38.0,
                        'srv-app-01' => 28.0,
                        'srv-queue-01' => 45.0,
                        'srv-storage-san' => 18.0,
                        'srv-edge-gw' => 14.0,
                        default => 22.0,
                    };

                    $cpuJitter = ((($step * 11) % 17) - 8) * 1.5;
                    $cpu = max(8.0, min(84.0, round($baseCpu + $cpuJitter, 1)));

                    $metricRecords[] = [
                        'server_id' => $srv->id,
                        'cpu_percent' => $cpu,
                        'ram_used_mb' => match ($srv->identifier) {
                            'srv-db-01' => 24500,
                            'srv-app-01' => 12400,
                            'srv-queue-01' => 8800,
                            'srv-storage-san' => 34200,
                            'srv-edge-gw' => 2800,
                            default => 7200,
                        },
                        'ram_total_mb' => match ($srv->identifier) {
                            'srv-db-01' => 32768,
                            'srv-app-01' => 16384,
                            'srv-queue-01' => 16384,
                            'srv-storage-san' => 65536,
                            'srv-edge-gw' => 4096,
                            default => 16384,
                        },
                        'disk_used_gb' => 412.5,
                        'disk_total_gb' => 1000.0,
                        'storage_free_gb' => 587.5,
                        'storage_total_gb' => 1000.0,
                        'temperature_c' => round(39.5 + ($cpu * 0.15), 1),
                        'net_rx_mbps' => round(18.4 + ((($step * 7) % 23) * 1.2), 2),
                        'net_tx_mbps' => round(24.8 + ((($step * 9) % 29) * 1.4), 2),
                        'network_connected' => true,
                        'network_name' => 'eth0 (10GbE SFP+)',
                        'network_speed_mbps' => 10000,
                        'network_ipv4' => $srv->ip_address,
                        'uptime_seconds' => $uptime,
                        'created_at' => $time->toDateTimeString(),
                    ];
                }

                if (count($metricRecords) >= 200) {
                    ServerMetric::query()->insert($metricRecords);
                    $metricRecords = [];
                }
            }
            if (! empty($metricRecords)) {
                ServerMetric::query()->insert($metricRecords);
            }
        }

        // 6. AI Predictions (Historical checks & today's nominal prediction)
        if (AiPrediction::query()->count() < 10) {
            $predictionsData = [
                [
                    'time' => $now->subDays(12),
                    'temp' => 22.1,
                    'power' => 1020.0,
                    'risk' => 'normal',
                    'health' => 97,
                    'fail_prob' => 2,
                    'anomaly' => 0.04,
                    'cause' => 'Nominal baseline',
                    'explanation' => 'Thermal and power parameters are operating well within normal thresholds.',
                    'recommendation' => 'Maintain standard sensor polling cadence.',
                ],
                [
                    'time' => $now->subDays(9),
                    'temp' => 22.6,
                    'power' => 1140.0,
                    'risk' => 'normal',
                    'health' => 95,
                    'fail_prob' => 4,
                    'anomaly' => 0.07,
                    'cause' => 'Routine business-hours workload',
                    'explanation' => 'Slight daytime thermal gradient on Rack 01; fully dissipated by CRAC unit 1.',
                    'recommendation' => 'No intervention required.',
                ],
                [
                    'time' => $now->subDays(6),
                    'temp' => 25.1,
                    'power' => 1520.0,
                    'risk' => 'warning',
                    'health' => 83,
                    'fail_prob' => 16,
                    'anomaly' => 0.28,
                    'cause' => 'Elevated rack temperature during scheduled UPS discharge test',
                    'explanation' => 'Inverter heat dissipation increased ambient rack temperature above 24.8°C.',
                    'recommendation' => 'Ensure secondary cooling damper remains open until inverter settles.',
                ],
                [
                    'time' => $now->subDays(4),
                    'temp' => 22.4,
                    'power' => 1080.0,
                    'risk' => 'normal',
                    'health' => 96,
                    'fail_prob' => 3,
                    'anomaly' => 0.06,
                    'cause' => 'Post-maintenance stabilized status',
                    'explanation' => 'Temperature normalized following filter replacement and UPS test sign-off.',
                    'recommendation' => 'Schedule next filter inspection in 60 days.',
                ],
                [
                    'time' => $now->subDays(2),
                    'temp' => 22.2,
                    'power' => 1095.0,
                    'risk' => 'normal',
                    'health' => 96,
                    'fail_prob' => 3,
                    'anomaly' => 0.05,
                    'cause' => 'Nominal operational envelope',
                    'explanation' => 'Airflow velocity, temperature, and electrical load indicators are stable.',
                    'recommendation' => 'All parameters nominal.',
                ],
                [
                    'time' => $now,
                    'temp' => 22.4,
                    'power' => 1115.0,
                    'risk' => 'normal',
                    'health' => 96,
                    'fail_prob' => 3,
                    'anomaly' => 0.06,
                    'cause' => 'Nominal operational envelope',
                    'explanation' => 'All rack thermal gradients and power draw indices are within ASHRAE TC 9.9 server room specifications.',
                    'recommendation' => 'All environmental parameters are healthy. Maintain standard monitoring cadence.',
                ],
            ];

            foreach ($predictionsData as $p) {
                AiPrediction::query()->create([
                    'temperature' => $p['temp'],
                    'power_w' => $p['power'],
                    'risk_level' => $p['risk'],
                    'rack_health_score' => $p['health'],
                    'failure_probability' => $p['fail_prob'],
                    'anomaly_score' => $p['anomaly'],
                    'main_cause' => $p['cause'],
                    'explanation' => $p['explanation'],
                    'recommendation' => $p['recommendation'],
                    'raw_prediction' => [
                        'model_version' => 'InfraGuard-ML-v2.1',
                        'inference_time_ms' => 42,
                        'confidence' => 0.984,
                    ],
                    'created_at' => $p['time'],
                    'updated_at' => $p['time'],
                ]);
            }
        }

        // 7. Maintenance Tasks (Spanning 2 weeks: Completed, In Progress, and Upcoming)
        if (MaintenanceTask::query()->count() < 5) {
            $task1 = MaintenanceTask::query()->create([
                'server_room' => 'Server Room 1',
                'maintenance_date' => $now->subDays(10)->toDateString(),
                'fix_description' => 'Clean primary air intake filters on Rack A & Rack B',
                'priority' => MaintenanceTask::PRIORITY_MEDIUM,
                'status' => MaintenanceTask::STATUS_COMPLETED,
                'assigned_to_user_id' => $karim->id,
                'created_by_user_id' => $departmentHead->id,
                'created_at' => $now->subDays(12),
                'updated_at' => $now->subDays(10),
            ]);

            MaintenanceTaskHistory::query()->create([
                'maintenance_task_id' => $task1->id,
                'actor_id' => $departmentHead->id,
                'action' => 'created',
                'description' => 'Scheduled bi-weekly intake filter maintenance.',
                'old_status' => null,
                'new_status' => MaintenanceTask::STATUS_PENDING,
                'created_at' => $now->subDays(12),
            ]);
            MaintenanceTaskHistory::query()->create([
                'maintenance_task_id' => $task1->id,
                'actor_id' => $karim->id,
                'action' => 'completed',
                'description' => 'Cleaned both filter cassettes. Verified intake velocity increased to 0.44 m/s.',
                'old_status' => MaintenanceTask::STATUS_IN_PROGRESS,
                'new_status' => MaintenanceTask::STATUS_COMPLETED,
                'created_at' => $now->subDays(10),
            ]);

            $task2 = MaintenanceTask::query()->create([
                'server_room' => 'Server Room 1',
                'maintenance_date' => $now->subDays(6)->toDateString(),
                'fix_description' => 'UPS-01 battery discharge test and transfer switch validation',
                'priority' => MaintenanceTask::PRIORITY_URGENT,
                'status' => MaintenanceTask::STATUS_COMPLETED,
                'assigned_to_user_id' => $youssef->id,
                'created_by_user_id' => $departmentHead->id,
                'created_at' => $now->subDays(8),
                'updated_at' => $now->subDays(6),
            ]);

            MaintenanceTaskHistory::query()->create([
                'maintenance_task_id' => $task2->id,
                'actor_id' => $departmentHead->id,
                'action' => 'created',
                'description' => 'High-priority quarterly load bank test on primary UPS battery modules.',
                'old_status' => null,
                'new_status' => MaintenanceTask::STATUS_PENDING,
                'created_at' => $now->subDays(8),
            ]);
            MaintenanceTaskHistory::query()->create([
                'maintenance_task_id' => $task2->id,
                'actor_id' => $youssef->id,
                'action' => 'completed',
                'description' => '14 kW simulated load sustained for 28 minutes. Cell impedance and discharge rates nominal.',
                'old_status' => MaintenanceTask::STATUS_IN_PROGRESS,
                'new_status' => MaintenanceTask::STATUS_COMPLETED,
                'created_at' => $now->subDays(6),
            ]);

            $task3 = MaintenanceTask::query()->create([
                'server_room' => 'Server Room 1',
                'maintenance_date' => $now->subDays(3)->toDateString(),
                'fix_description' => 'SAN Storage ZFS firmware update v4.8.2 and pool scrub',
                'priority' => MaintenanceTask::PRIORITY_HIGH,
                'status' => MaintenanceTask::STATUS_COMPLETED,
                'assigned_to_user_id' => $sarra->id,
                'created_by_user_id' => $departmentHead->id,
                'created_at' => $now->subDays(5),
                'updated_at' => $now->subDays(3),
            ]);

            MaintenanceTaskHistory::query()->create([
                'maintenance_task_id' => $task3->id,
                'actor_id' => $sarra->id,
                'action' => 'completed',
                'description' => 'Updated dual storage controllers with rolling failover. ZFS pool scrub passed with 0 checksum errors.',
                'old_status' => MaintenanceTask::STATUS_IN_PROGRESS,
                'new_status' => MaintenanceTask::STATUS_COMPLETED,
                'created_at' => $now->subDays(3),
            ]);

            $task4 = MaintenanceTask::query()->create([
                'server_room' => 'Server Room 1',
                'maintenance_date' => $now->toDateString(),
                'fix_description' => 'Rear exhaust cable bundling and thermal duct inspection',
                'priority' => MaintenanceTask::PRIORITY_MEDIUM,
                'status' => MaintenanceTask::STATUS_IN_PROGRESS,
                'assigned_to_user_id' => $karim->id,
                'created_by_user_id' => $departmentHead->id,
                'created_at' => $now->subDay(),
                'updated_at' => $now,
            ]);

            MaintenanceTaskHistory::query()->create([
                'maintenance_task_id' => $task4->id,
                'actor_id' => $karim->id,
                'action' => 'started',
                'description' => 'Re-organizing patch cabling to optimize hot aisle extraction.',
                'old_status' => MaintenanceTask::STATUS_ASSIGNED,
                'new_status' => MaintenanceTask::STATUS_IN_PROGRESS,
                'created_at' => $now->subHours(2),
            ]);

            MaintenanceTask::query()->create([
                'server_room' => 'Server Room 1',
                'maintenance_date' => $now->addDays(3)->toDateString(),
                'fix_description' => 'Routine calibration of ambient temperature & humidity sensors',
                'priority' => MaintenanceTask::PRIORITY_LOW,
                'status' => MaintenanceTask::STATUS_PENDING,
                'assigned_to_user_id' => $youssef->id,
                'created_by_user_id' => $departmentHead->id,
                'created_at' => $now->subHours(4),
                'updated_at' => $now->subHours(4),
            ]);
        }

        // 8. Weekly Reports & AI Summaries
        if (Report::query()->count() < 2) {
            $report1 = Report::query()->create([
                'type' => 'weekly',
                'source' => 'system',
                'title' => 'Weekly Server Room Climate & Telemetry Report - Week 46',
                'period_start' => $twoWeeksAgo,
                'period_end' => $twoWeeksAgo->addDays(7),
                'status' => 'completed',
                'generated_by' => $departmentHead->id,
                'generated_at' => $twoWeeksAgo->addDays(7),
                'summary' => 'Mean temperature held steady at 22.3°C with 46.5% average humidity. All servers maintained 100% uptime with no thermal throttling.',
                'metrics_snapshot' => [
                    'avg_temperature' => 22.3,
                    'max_temperature' => 23.9,
                    'avg_humidity' => 46.5,
                    'avg_power_w' => 1120.4,
                    'total_energy_kwh' => 188.2,
                    'server_uptime_pct' => 100.0,
                ],
                'anomalies' => [],
                'created_at' => $twoWeeksAgo->addDays(7),
                'updated_at' => $twoWeeksAgo->addDays(7),
            ]);

            ReportAiSummary::query()->create([
                'report_id' => $report1->id,
                'provider' => 'gemini',
                'model' => 'gemini-1.5-pro',
                'status' => 'completed',
                'summary_text' => 'Infrastructure telemetry demonstrates continuous environmental stability throughout Week 46. Thermal envelope adhered strictly to ASHRAE Class A1 guidelines.',
                'observations' => "1. Ambient room temperature remained within +/- 0.8°C of setpoint.\n2. Inbound power showed minimal harmonic distortion.\n3. Zero packet drops on core switch trunks.",
                'recommendations' => "1. Continue scheduled air filter maintenance.\n2. Proceed with planned UPS discharge testing next week.",
                'generated_at' => $twoWeeksAgo->addDays(7),
            ]);

            $report2 = Report::query()->create([
                'type' => 'weekly',
                'source' => 'system',
                'title' => 'Weekly Server Room Climate & Telemetry Report - Week 47',
                'period_start' => $twoWeeksAgo->addDays(7),
                'period_end' => $now,
                'status' => 'completed',
                'generated_by' => $departmentHead->id,
                'generated_at' => $now->subHours(2),
                'summary' => 'Weekly metrics recorded nominal operational status. Minor thermal rise during the Day 8 UPS battery test was smoothly stabilized by secondary CRAC ventilation.',
                'metrics_snapshot' => [
                    'avg_temperature' => 22.5,
                    'max_temperature' => 25.1,
                    'avg_humidity' => 46.8,
                    'avg_power_w' => 1165.8,
                    'total_energy_kwh' => 195.8,
                    'server_uptime_pct' => 99.98,
                ],
                'anomalies' => [
                    [
                        'metric' => 'temperature',
                        'value' => 25.1,
                        'threshold' => 25.0,
                        'timestamp' => $now->subDays(6)->toIso8601String(),
                        'note' => 'Controlled thermal elevation during UPS discharge trial.',
                    ],
                ],
                'created_at' => $now->subHours(2),
                'updated_at' => $now->subHours(2),
            ]);

            ReportAiSummary::query()->create([
                'report_id' => $report2->id,
                'provider' => 'gemini',
                'model' => 'gemini-1.5-pro',
                'status' => 'completed',
                'summary_text' => 'Week 47 showed high infrastructure reliability and successful execution of critical electrical maintenance. Overall cluster uptime reached 99.98%.',
                'observations' => "1. UPS load test completed safely with 28 min runtime headroom.\n2. Storage SAN patch v4.8.2 deployed with zero transaction lag.\n3. Intake filters clean and air velocity verified at 0.44 m/s.",
                'recommendations' => "1. Conclude rear exhaust cable grooming.\n2. Calibrate DHT22 sensor probe #1 during upcoming maintenance window.",
                'generated_at' => $now->subHours(2),
            ]);
        }

        // 9. Knowledge Base Problems & Solutions
        if (Problem::query()->count() < 2) {
            $problem1 = Problem::query()->create([
                'user_id' => $karim->id,
                'title' => 'Airflow resistance on Rack A intake filter door',
                'description' => 'Static pressure increased slightly due to dust accumulation on front mesh after building vent maintenance.',
                'status' => 'resolved',
                'created_at' => $now->subDays(11),
                'updated_at' => $now->subDays(10),
            ]);

            Solution::query()->create([
                'problem_id' => $problem1->id,
                'user_id' => $karim->id,
                'body' => 'Replaced intake foam filter with high-flow particulate cassette. Differential air pressure returned to standard 18 Pa.',
                'created_at' => $now->subDays(10),
                'updated_at' => $now->subDays(10),
            ]);

            $problem2 = Problem::query()->create([
                'user_id' => $sarra->id,
                'title' => 'Intermittent packet jitter on secondary SAN backup VLAN',
                'description' => 'Observed 1.4% packet retransmissions during nightly rsync snapshot sync on interface eth2.',
                'status' => 'resolved',
                'created_at' => $now->subDays(5),
                'updated_at' => $now->subDays(4),
            ]);

            Solution::query()->create([
                'problem_id' => $problem2->id,
                'user_id' => $sarra->id,
                'body' => 'Cleaned optical ferrule and re-seated SFP+ 10GbE transceiver on top-of-rack switch port 14. Rx optical power stabilized at -3.4 dBm with zero frame errors.',
                'created_at' => $now->subDays(4),
                'updated_at' => $now->subDays(4),
            ]);
        }

        // 10. Team Chat Messages
        if (Message::query()->count() < 8) {
            $messages = [
                ['user' => $karim, 'days' => 12, 'body' => 'Good morning team. I am starting the routine intake filter check on Rack A and B today.'],
                ['user' => $departmentHead, 'days' => 12, 'body' => 'Thanks Karim. Please log the face velocity before and after cleaning so we can track the differential.'],
                ['user' => $karim, 'days' => 10, 'body' => 'Filters serviced and reinstalled. Velocity is back up to 0.44 m/s across both bays.'],
                ['user' => $departmentHead, 'days' => 7, 'body' => 'Reminder: UPS-01 battery discharge test is scheduled for tomorrow morning at 09:00.'],
                ['user' => $youssef, 'days' => 6, 'body' => 'UPS test started. Main feed switched to inverter bank; load running smoothly at 14 kW.'],
                ['user' => $youssef, 'days' => 6, 'body' => 'Discharge test finished with 28 minutes autonomous runtime. All 32 cells tested within nominal impedance.'],
                ['user' => $sarra, 'days' => 3, 'body' => 'SAN Storage ZFS firmware update v4.8.2 applied cleanly with rolling controller failover. Zero downtime.'],
                ['user' => $departmentHead, 'days' => 1, 'body' => 'Excellent work everyone. Telemetry charts and AI health index are holding steady at 96% safe.'],
                ['user' => $karim, 'days' => 0, 'body' => 'Currently wrapping up cable bundling behind Rack 02 hot aisle.'],
            ];

            foreach ($messages as $msg) {
                Message::query()->create([
                    'user_id' => $msg['user']->id,
                    'body' => $msg['body'],
                    'created_at' => $now->subDays($msg['days']),
                    'updated_at' => $now->subDays($msg['days']),
                ]);
            }
        }

        // 11. User Notifications (for Department Head)
        if (UserNotification::query()->where('user_id', $departmentHead->id)->count() < 4) {
            $notifications = [
                [
                    'type' => 'report.ready',
                    'title' => 'Weekly Report Available',
                    'body' => 'Weekly Server Room Climate & Telemetry Report (Week 47) has been generated.',
                    'url' => '/reports',
                    'read' => true,
                    'created_at' => $now->subHours(2),
                ],
                [
                    'type' => 'maintenance.completed',
                    'title' => 'Maintenance Task Completed',
                    'body' => 'SAN Storage ZFS firmware update v4.8.2 marked as completed by Sarra Mansour.',
                    'url' => '/maintenance',
                    'read' => true,
                    'created_at' => $now->subDays(3),
                ],
                [
                    'type' => 'maintenance.completed',
                    'title' => 'UPS Discharge Test Completed',
                    'body' => 'UPS-01 battery discharge test completed with 28 min runtime by Youssef Trabelsi.',
                    'url' => '/maintenance',
                    'read' => true,
                    'created_at' => $now->subDays(6),
                ],
                [
                    'type' => 'ai.telemetry',
                    'title' => 'AI Telemetry Status: Optimal',
                    'body' => 'Server room rack health score updated to 96% (Safe envelope).',
                    'url' => '/dashboard',
                    'read' => false,
                    'created_at' => $now->subHours(5),
                ],
            ];

            foreach ($notifications as $notif) {
                UserNotification::query()->create([
                    'user_id' => $departmentHead->id,
                    'type' => $notif['type'],
                    'title' => $notif['title'],
                    'body' => $notif['body'],
                    'url' => $notif['url'],
                    'data' => [],
                    'read_at' => $notif['read'] ? $notif['created_at'] : null,
                    'created_at' => $notif['created_at'],
                    'updated_at' => $notif['created_at'],
                ]);
            }
        }

        // 12. Audit Logs
        if (AuditLog::query()->count() < 5) {
            $logs = [
                ['actor_id' => $departmentHead->id, 'action' => 'user.login', 'target_type' => User::class, 'target_id' => $departmentHead->id, 'time' => $now->subDays(14)],
                ['actor_id' => $departmentHead->id, 'action' => 'maintenance.created', 'target_type' => MaintenanceTask::class, 'target_id' => 1, 'time' => $now->subDays(12)],
                ['actor_id' => $karim->id, 'action' => 'maintenance.completed', 'target_type' => MaintenanceTask::class, 'target_id' => 1, 'time' => $now->subDays(10)],
                ['actor_id' => $youssef->id, 'action' => 'maintenance.completed', 'target_type' => MaintenanceTask::class, 'target_id' => 2, 'time' => $now->subDays(6)],
                ['actor_id' => $sarra->id, 'action' => 'maintenance.completed', 'target_type' => MaintenanceTask::class, 'target_id' => 3, 'time' => $now->subDays(3)],
                ['actor_id' => $departmentHead->id, 'action' => 'report.generated', 'target_type' => Report::class, 'target_id' => 2, 'time' => $now->subHours(2)],
            ];

            foreach ($logs as $l) {
                AuditLog::query()->create([
                    'actor_id' => $l['actor_id'],
                    'action' => $l['action'],
                    'target_type' => $l['target_type'],
                    'target_id' => $l['target_id'],
                    'metadata' => ['ip' => '127.0.0.1', 'user_agent' => 'InfraGuard/Console'],
                    'created_at' => $l['time'],
                ]);
            }
        }
    }
}
