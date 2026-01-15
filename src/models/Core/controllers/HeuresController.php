<?php
require_once __DIR__ . '/../models/Heures.php';

class HeuresController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new Heures($pdo);
    }

    private function monthRange(string $ym): array {
        // $ym expected as YYYY-MM
        $start = $ym . '-01';
        $end = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

    private function formatSecondsToHHMM(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function getOverviewForMonth(string $yearMonth): array {
        list($start, $end) = $this->monthRange($yearMonth);

        $totalPointages = $this->model->getTotalPointagesBetween($start, $end);
        $totalArrivals = $this->model->getTotalArrivalsBetween($start, $end);
        $recent = $this->model->getRecentPointages(10);
        $retards = $this->model->getRetardsBetween($start, $end, 50);
        $totalSeconds = $this->model->getTotalWorkedSecondsBetween($start, $end);
        $employees = $this->model->getEmployeesHoursBetween($start, $end);

        // previous month comparison
        $prevMonth = date('Y-m', strtotime($start . ' -1 month'));
        list($pstart, $pend) = $this->monthRange($prevMonth);
        $prevSeconds = $this->model->getTotalWorkedSecondsBetween($pstart, $pend);

        $percentChange = null;
        if ($prevSeconds > 0) {
            $percentChange = round((($totalSeconds - $prevSeconds) / max(1, $prevSeconds)) * 100, 1);
        }

        // Présences aujourd'hui
        $todayCounts = $this->model->getPresenceCountsForDate(date('Y-m-d'));

        // format recent activities
        $recentFormatted = array_map(function($r){
            return [
                'id' => $r['id'] ?? null,
                'employe' => trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')),
                'type' => $r['type'] ?? null,
                'date' => isset($r['date']) ? date('d/m/Y', strtotime($r['date'])) : null,
                'time' => $r['heure'] ?? (isset($r['date_heure']) ? date('H:i', strtotime($r['date_heure'])) : null),
            ];
        }, $recent);

        // format retards
        $retardsFormatted = array_map(function($r){
            return [
                'id' => $r['id'] ?? null,
                'employe' => trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')),
                'date_heure' => $r['date_heure'] ?? null,
                'date' => isset($r['date_heure']) ? date('d/m/Y', strtotime($r['date_heure'])) : null,
                'time' => isset($r['date_heure']) ? date('H:i', strtotime($r['date_heure'])) : null,
                'retard_minutes' => (int)($r['retard_minutes'] ?? 0)
            ];
        }, $retards);

        return [
            'month' => $yearMonth,
            'start' => $start,
            'end' => $end,
            'total_pointages' => $totalPointages,
            'total_arrivals' => $totalArrivals,
            'recent' => $recentFormatted,
            'retards' => $retardsFormatted,
            'total_seconds' => $totalSeconds,
            'total_hours' => $this->formatSecondsToHHMM($totalSeconds),
            'employees' => $employees,
            'prev_month' => $prevMonth,
            'prev_seconds' => $prevSeconds,
            'percent_change' => $percentChange,
            'today_counts' => $todayCounts
        ];
    }
}
