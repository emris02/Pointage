<?php
// src/services/WorkTimeCalculator.php

/**
 * WorkTimeCalculator - Service de calcul intelligent du temps de travail
 * Gère les pauses automatiques, les heures supplémentaires et les statistiques
 */
class WorkTimeCalculator {
    private PDO $pdo;
    private array $config;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, ?LoggerInterface $logger = null) {
        $this->pdo = $pdo;
        $this->logger = $logger ?? new NullLogger();
        $this->loadConfig();
    }

    /**
     * Charge la configuration depuis la base de données ou des paramètres par défaut
     */
    private function loadConfig(): void {
        // Configuration par défaut
        $this->config = [
            'break_policy' => [
                'min_work_for_break' => 4 * 3600, // 4 heures de travail pour avoir droit à une pause
                'break_duration' => 3600, // 1 heure de pause
                'break_window_start' => '12:00', // Heure de début de la fenêtre de pause recommandée
                'break_window_end' => '14:00', // Heure de fin de la fenêtre de pause recommandée
                'auto_break_enabled' => true,
                'max_breaks_per_day' => 2
            ],
            'working_hours' => [
                'default' => [
                    'daily_hours' => 8 * 3600, // 8 heures par jour
                    'weekly_hours' => 40 * 3600, // 40 heures par semaine
                    'monthly_hours' => 160 * 3600 // 160 heures par mois
                ],
                'overtime' => [
                    'threshold' => 8 * 3600, // Heures supplémentaires après 8h/jour
                    'rate_1' => 1.25, // Taux pour les premières heures supplémentaires
                    'rate_2' => 1.5, // Taux pour les heures supplémentaires suivantes
                    'max_overtime_per_day' => 4 * 3600 // Maximum 4h supplémentaires/jour
                ]
            ],
            'schedule' => [
                'work_days' => [1, 2, 3, 4, 5], // Lundi à vendredi
                'work_start' => '08:00',
                'work_end' => '17:00',
                'flexible_hours' => false,
                'core_hours' => ['09:00', '16:00'] // Heures de présence obligatoire si flexible
            ],
            'validation' => [
                'max_continuous_work' => 6 * 3600, // Maximum 6h continues sans pause
                'min_break_between_shifts' => 11 * 3600, // 11h minimum entre deux shifts
                'max_weekly_hours' => 48 * 3600 // Maximum légal hebdomadaire
            ]
        ];

        // Essayer de charger la configuration depuis la base de données
        $this->loadConfigFromDatabase();
    }

    /**
     * Charge la configuration depuis la table de paramètres
     */
    private function loadConfigFromDatabase(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT param_key, param_value, param_type 
                FROM system_parameters 
                WHERE category IN ('work_time', 'breaks', 'schedule')
            ");
            
            $params = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($params as $param) {
                $key = $param['param_key'];
                $value = $this->parseParameterValue($param['param_value'], $param['param_type']);
                
                // Mapper les clés à la structure de configuration
                $this->mapParameterToConfig($key, $value);
            }
        } catch (Exception $e) {
            $this->logger->warning('Impossible de charger la configuration depuis la base', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Parse les valeurs des paramètres selon leur type
     */
    private function parseParameterValue(string $value, string $type) {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
                return json_decode($value, true) ?? [];
            case 'time':
                return $value; // Retourne la chaîne de temps
            default:
                return $value;
        }
    }

    /**
     * Mappe un paramètre à la structure de configuration
     */
    private function mapParameterToConfig(string $key, $value): void {
        $mapping = [
            'break_min_work_hours' => ['break_policy', 'min_work_for_break'],
            'break_duration_minutes' => ['break_policy', 'break_duration'],
            'break_window_start' => ['break_policy', 'break_window_start'],
            'break_window_end' => ['break_policy', 'break_window_end'],
            'auto_break_enabled' => ['break_policy', 'auto_break_enabled'],
            'daily_work_hours' => ['working_hours', 'default', 'daily_hours'],
            'weekly_work_hours' => ['working_hours', 'default', 'weekly_hours'],
            'monthly_work_hours' => ['working_hours', 'default', 'monthly_hours'],
            'overtime_threshold' => ['working_hours', 'overtime', 'threshold'],
            'overtime_rate_1' => ['working_hours', 'overtime', 'rate_1'],
            'overtime_rate_2' => ['working_hours', 'overtime', 'rate_2'],
            'work_start_time' => ['schedule', 'work_start'],
            'work_end_time' => ['schedule', 'work_end'],
            'flexible_hours' => ['schedule', 'flexible_hours'],
            'max_continuous_work' => ['validation', 'max_continuous_work'],
            'min_break_between_shifts' => ['validation', 'min_break_between_shifts'],
            'max_weekly_hours' => ['validation', 'max_weekly_hours']
        ];

        if (isset($mapping[$key])) {
            $path = $mapping[$key];
            $this->setConfigValue($this->config, $path, $value);
        }
    }

    /**
     * Définit une valeur dans le tableau de configuration
     */
    private function setConfigValue(array &$config, array $path, $value): void {
        $temp = &$config;
        
        foreach ($path as $key) {
            if (!isset($temp[$key]) || !is_array($temp[$key])) {
                break;
            }
            $temp = &$temp[$key];
        }
        
        $temp = $value;
    }

    /**
     * Calcule le temps de travail effectif entre deux timestamps
     */
    public function calculateWorkTime(string $startTime, string $endTime, int $employeId): array {
        try {
            $this->validateTimeRange($startTime, $endTime);
            
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
            $date = $start->format('Y-m-d');
            
            // 1. Calculer le temps brut
            $rawDuration = $this->calculateRawDuration($start, $end);
            
            // 2. Récupérer les pauses existantes pour cette période
            $existingBreaks = $this->getExistingBreaks($employeId, $start, $end);
            
            // 3. Calculer les pauses automatiques si nécessaire
            $autoBreaks = $this->calculateAutomaticBreaks($rawDuration, $employeId, $start, $existingBreaks);
            
            // 4. Fusionner toutes les pauses
            $allBreaks = array_merge($existingBreaks, $autoBreaks);
            
            // 5. Calculer le temps de travail effectif (brut - pauses)
            $effectiveWork = $this->calculateEffectiveWork($rawDuration, $allBreaks);
            
            // 6. Vérifier les contraintes légales
            $constraints = $this->checkConstraints($employeId, $date, $effectiveWork);
            
            // 7. Calculer les heures supplémentaires si nécessaire
            $overtime = $this->calculateOvertime($effectiveWork, $employeId, $date);
            
            // 8. Enregistrer les pauses automatiques si créées
            $this->recordAutoBreaks($employeId, $autoBreaks);
            
            // 9. Mettre à jour les statistiques de l'employé
            $this->updateEmployeeStats($employeId, $date, $effectiveWork, $overtime);
            
            return [
                'status' => 'success',
                'period' => [
                    'start' => $startTime,
                    'end' => $endTime,
                    'date' => $date
                ],
                'durations' => [
                    'raw' => $this->formatDuration($rawDuration),
                    'raw_seconds' => $rawDuration,
                    'breaks' => $this->formatBreaks($allBreaks),
                    'breaks_total' => $this->calculateTotalBreaks($allBreaks),
                    'effective' => $this->formatDuration($effectiveWork),
                    'effective_seconds' => $effectiveWork
                ],
                'analysis' => [
                    'breaks_needed' => count($autoBreaks) > 0,
                    'auto_breaks_created' => count($autoBreaks),
                    'constraints_violations' => $constraints['violations'],
                    'overtime' => $overtime,
                    'recommendations' => $this->generateRecommendations($rawDuration, $effectiveWork, $constraints)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erreur calcul temps travail', [
                'employee_id' => $employeId,
                'start' => $startTime,
                'end' => $endTime,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Valide la plage temporelle
     */
    private function validateTimeRange(string $startTime, string $endTime): void {
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);
        
        if ($end < $start) {
            throw new InvalidArgumentException("L'heure de fin doit être après l'heure de début");
        }
        
        // Vérifier que les dates sont dans une plage raisonnable (max 24h)
        $interval = $start->diff($end);
        $totalHours = ($interval->days * 24) + $interval->h;
        
        if ($totalHours > 24) {
            throw new InvalidArgumentException("La plage temporelle ne peut pas dépasser 24 heures");
        }
    }

    /**
     * Calcule la durée brute entre deux dates
     */
    private function calculateRawDuration(DateTime $start, DateTime $end): int {
        $interval = $start->diff($end);
        return ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    /**
     * Récupère les pauses existantes pour la période
     */
    private function getExistingBreaks(int $employeId, DateTime $start, DateTime $end): array {
        $breaks = [];
        
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                debut as start,
                fin as end,
                TIMESTAMPDIFF(SECOND, debut, fin) as duration_seconds,
                type,
                auto_generated,
                description
            FROM pauses 
            WHERE employe_id = ?
            AND (
                (debut BETWEEN ? AND ?) OR
                (fin BETWEEN ? AND ?) OR
                (debut <= ? AND fin >= ?)
            )
            AND status = 'valid'
            ORDER BY debut
        ");
        
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');
        
        $stmt->execute([$employeId, $startStr, $endStr, $startStr, $endStr, $startStr, $endStr]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $breaks[] = [
                'id' => $row['id'],
                'start' => $row['start'],
                'end' => $row['end'],
                'duration' => (int)$row['duration_seconds'],
                'type' => $row['type'],
                'auto_generated' => (bool)$row['auto_generated'],
                'description' => $row['description']
            ];
        }
        
        return $breaks;
    }

    /**
     * Calcule les pauses automatiques nécessaires
     */
    private function calculateAutomaticBreaks(int $rawDuration, int $employeId, DateTime $start, array $existingBreaks): array {
        if (!$this->config['break_policy']['auto_break_enabled']) {
            return [];
        }
        
        // Calculer le temps de travail continu sans pause
        $continuousWork = $this->calculateContinuousWorkWithoutBreak($start, $existingBreaks);
        
        // Vérifier si une pause est nécessaire
        $needsBreak = $this->needsAutomaticBreak($rawDuration, $continuousWork, $employeId, $start->format('Y-m-d'));
        
        if (!$needsBreak) {
            return [];
        }
        
        // Calculer le moment optimal pour la pause
        $optimalBreakTime = $this->calculateOptimalBreakTime($start, $rawDuration);
        
        // Vérifier qu'il reste assez de temps pour la pause
        $remainingTime = $rawDuration - ($optimalBreakTime - $start->getTimestamp());
        $minRemaining = $this->config['break_policy']['break_duration'] + 3600; // pause + 1h après
        
        if ($remainingTime < $minRemaining) {
            $optimalBreakTime = $start->getTimestamp() + ($rawDuration - $minRemaining);
        }
        
        // Créer la pause automatique
        $breakStart = new DateTime('@' . $optimalBreakTime);
        $breakEnd = clone $breakStart;
        $breakEnd->modify('+' . $this->config['break_policy']['break_duration'] . ' seconds');
        
        return [[
            'start' => $breakStart->format('Y-m-d H:i:s'),
            'end' => $breakEnd->format('Y-m-d H:i:s'),
            'duration' => $this->config['break_policy']['break_duration'],
            'type' => 'auto',
            'auto_generated' => true,
            'reason' => 'Travail continu de plus de ' . gmdate('H:i', $this->config['break_policy']['min_work_for_break'])
        ]];
    }

    /**
     * Calcule le temps de travail continu sans pause
     */
    private function calculateContinuousWorkWithoutBreak(DateTime $start, array $existingBreaks): int {
        if (empty($existingBreaks)) {
            return time() - $start->getTimestamp();
        }
        
        // Trier les pauses par heure de début
        usort($existingBreaks, function($a, $b) {
            return strtotime($a['start']) <=> strtotime($b['start']);
        });
        
        // Trouver la dernière pause
        $lastBreak = end($existingBreaks);
        $lastBreakEnd = strtotime($lastBreak['end']);
        
        return time() - $lastBreakEnd;
    }

    /**
     * Détermine si une pause automatique est nécessaire
     */
    private function needsAutomaticBreak(int $rawDuration, int $continuousWork, int $employeId, string $date): bool {
        // Vérifier si l'employé a déjà pris assez de pauses aujourd'hui
        $breakCount = $this->getBreakCountToday($employeId, $date);
        
        if ($breakCount >= $this->config['break_policy']['max_breaks_per_day']) {
            return false;
        }
        
        // Vérifier le temps de travail continu
        if ($continuousWork >= $this->config['break_policy']['min_work_for_break']) {
            return true;
        }
        
        // Vérifier la durée totale de travail
        if ($rawDuration >= $this->config['break_policy']['min_work_for_break'] * 1.5) {
            return true;
        }
        
        return false;
    }

    /**
     * Compte le nombre de pauses prises aujourd'hui
     */
    private function getBreakCountToday(int $employeId, string $date): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM pauses 
            WHERE employe_id = ? 
            AND DATE(debut) = ?
        ");
        
        $stmt->execute([$employeId, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'];
    }

    /**
     * Calcule le moment optimal pour une pause
     */
    private function calculateOptimalBreakTime(DateTime $start, int $totalDuration): int {
        $startTimestamp = $start->getTimestamp();
        
        // Essayer de placer la pause dans la fenêtre recommandée
        $breakWindowStart = strtotime($start->format('Y-m-d') . ' ' . $this->config['break_policy']['break_window_start']);
        $breakWindowEnd = strtotime($start->format('Y-m-d') . ' ' . $this->config['break_policy']['break_window_end']);
        
        // Si on est dans la fenêtre, prendre maintenant
        $now = time();
        if ($now >= $breakWindowStart && $now <= $breakWindowEnd) {
            return $now;
        }
        
        // Sinon, calculer en fonction du temps écoulé
        $elapsed = $now - $startTimestamp;
        $optimalTime = $startTimestamp + $this->config['break_policy']['min_work_for_break'];
        
        // Ajuster pour ne pas dépasser la fin
        $maxTime = $startTimestamp + $totalDuration - $this->config['break_policy']['break_duration'] - 3600;
        
        return min($optimalTime, $maxTime);
    }

    /**
     * Calcule le temps de travail effectif (brut - pauses)
     */
    private function calculateEffectiveWork(int $rawDuration, array $breaks): int {
        $breakDuration = $this->calculateTotalBreaks($breaks);
        $effective = $rawDuration - $breakDuration;
        
        // S'assurer que le temps effectif n'est pas négatif
        return max(0, $effective);
    }

    /**
     * Calcule la durée totale des pauses
     */
    private function calculateTotalBreaks(array $breaks): int {
        $total = 0;
        foreach ($breaks as $break) {
            $total += $break['duration'];
        }
        return $total;
    }

    /**
     * Vérifie les contraintes légales et de sécurité
     */
    private function checkConstraints(int $employeId, string $date, int $effectiveWork): array {
        $violations = [];
        
        // 1. Vérifier le temps de travail quotidien
        $dailyWork = $this->getWorkedHoursToday($employeId, $date) + $effectiveWork;
        
        if ($dailyWork > $this->config['validation']['max_weekly_hours'] / 5) {
            $violations[] = [
                'type' => 'max_daily_hours',
                'current' => gmdate('H:i', $dailyWork),
                'max' => gmdate('H:i', $this->config['validation']['max_weekly_hours'] / 5),
                'message' => 'Temps de travail quotidien excessif'
            ];
        }
        
        // 2. Vérifier le temps de travail continu
        if ($effectiveWork > $this->config['validation']['max_continuous_work']) {
            $violations[] = [
                'type' => 'max_continuous_work',
                'current' => gmdate('H:i', $effectiveWork),
                'max' => gmdate('H:i', $this->config['validation']['max_continuous_work']),
                'message' => 'Temps de travail continu trop long sans pause'
            ];
        }
        
        // 3. Vérifier le temps de repos entre deux journées
        $lastDeparture = $this->getLastDepartureTime($employeId);
        
        if ($lastDeparture) {
            $nextArrival = new DateTime($date . ' ' . $this->config['schedule']['work_start']);
            $restHours = ($nextArrival->getTimestamp() - strtotime($lastDeparture)) / 3600;
            
            if ($restHours < ($this->config['validation']['min_break_between_shifts'] / 3600)) {
                $violations[] = [
                    'type' => 'min_rest_between_shifts',
                    'current' => round($restHours, 1) . 'h',
                    'min' => $this->config['validation']['min_break_between_shifts'] / 3600 . 'h',
                    'message' => 'Temps de repos insuffisant entre deux journées'
                ];
            }
        }
        
        return [
            'violations' => $violations,
            'passed' => empty($violations)
        ];
    }

    /**
     * Récupère les heures travaillées aujourd'hui
     */
    private function getWorkedHoursToday(int $employeId, string $date): int {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, debut, fin)), 0) as total_seconds
            FROM travail_periods 
            WHERE employe_id = ? 
            AND DATE(debut) = ?
        ");
        
        $stmt->execute([$employeId, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total_seconds'];
    }

    /**
     * Récupère l'heure de départ de la veille
     */
    private function getLastDepartureTime(int $employeId): ?string {
        $stmt = $this->pdo->prepare("
            SELECT MAX(fin) as last_departure
            FROM travail_periods 
            WHERE employe_id = ? 
            AND DATE(fin) < CURDATE()
        ");
        
        $stmt->execute([$employeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_departure'] ?? null;
    }

    /**
     * Calcule les heures supplémentaires
     */
    private function calculateOvertime(int $effectiveWork, int $employeId, string $date): array {
        $dailyThreshold = $this->config['working_hours']['overtime']['threshold'];
        $weeklyThreshold = $this->config['working_hours']['default']['weekly_hours'];
        
        // Calculer les heures quotidiennes
        $dailyOvertime = max(0, $effectiveWork - $dailyThreshold);
        
        // Calculer les heures hebdomadaires
        $weeklyWork = $this->getWorkedHoursThisWeek($employeId) + $effectiveWork;
        $weeklyOvertime = max(0, $weeklyWork - $weeklyThreshold);
        
        // Limiter les heures supplémentaires quotidiennes
        $maxDailyOvertime = $this->config['working_hours']['overtime']['max_overtime_per_day'];
        $dailyOvertime = min($dailyOvertime, $maxDailyOvertime);
        
        // Calculer les taux
        $rate1Hours = min($dailyOvertime, 2 * 3600); // 2 premières heures à taux 1
        $rate2Hours = max(0, $dailyOvertime - (2 * 3600)); // Heures suivantes à taux 2
        
        return [
            'daily_overtime' => $this->formatDuration($dailyOvertime),
            'daily_overtime_seconds' => $dailyOvertime,
            'weekly_overtime' => $this->formatDuration($weeklyOvertime),
            'weekly_overtime_seconds' => $weeklyOvertime,
            'rate_1_hours' => $this->formatDuration($rate1Hours),
            'rate_1_seconds' => $rate1Hours,
            'rate_2_hours' => $this->formatDuration($rate2Hours),
            'rate_2_seconds' => $rate2Hours,
            'total_overtime_cost' => $this->calculateOvertimeCost($rate1Hours, $rate2Hours, $employeId)
        ];
    }

    /**
     * Récupère les heures travaillées cette semaine
     */
    private function getWorkedHoursThisWeek(int $employeId): int {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, debut, fin)), 0) as total_seconds
            FROM travail_periods 
            WHERE employe_id = ? 
            AND YEARWEEK(debut, 1) = YEARWEEK(CURDATE(), 1)
        ");
        
        $stmt->execute([$employeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total_seconds'];
    }

    /**
     * Calcule le coût des heures supplémentaires
     */
    private function calculateOvertimeCost(int $rate1Seconds, int $rate2Seconds, int $employeId): float {
        // Récupérer le salaire horaire de l'employé
        $hourlyRate = $this->getHourlyRate($employeId);
        
        $rate1Hours = $rate1Seconds / 3600;
        $rate2Hours = $rate2Seconds / 3600;
        
        $cost = ($rate1Hours * $hourlyRate * $this->config['working_hours']['overtime']['rate_1']) +
                ($rate2Hours * $hourlyRate * $this->config['working_hours']['overtime']['rate_2']);
        
        return round($cost, 2);
    }

    /**
     * Récupère le salaire horaire de l'employé
     */
    private function getHourlyRate(int $employeId): float {
        $stmt = $this->pdo->prepare("
            SELECT salaire_horaire 
            FROM employes 
            WHERE id = ?
        ");
        
        $stmt->execute([$employeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float)($result['salaire_horaire'] ?? 0);
    }

    /**
     * Enregistre les pauses automatiques
     */
    private function recordAutoBreaks(int $employeId, array $autoBreaks): void {
        foreach ($autoBreaks as $break) {
            if ($break['auto_generated']) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pauses (
                        employe_id, debut, fin, duree, type, 
                        auto_generated, description, status, created_at
                    ) VALUES (?, ?, ?, ?, 'auto', 1, ?, 'valid', NOW())
                ");
                
                $description = "Pause automatique - " . ($break['reason'] ?? 'Travail continu');
                $stmt->execute([
                    $employeId,
                    $break['start'],
                    $break['end'],
                    $break['duration'],
                    $description
                ]);
                
                $this->logger->info('Pause automatique enregistrée', [
                    'employee_id' => $employeId,
                    'break_start' => $break['start'],
                    'break_end' => $break['end'],
                    'duration' => $break['duration']
                ]);
            }
        }
    }

    /**
     * Met à jour les statistiques de l'employé
     */
    private function updateEmployeeStats(int $employeId, string $date, int $workSeconds, array $overtime): void {
        // Mettre à jour les statistiques quotidiennes
        $stmt = $this->pdo->prepare("
            INSERT INTO employee_stats_daily (
                employe_id, date, work_seconds, breaks_seconds, overtime_seconds, 
                overtime_cost, updated_at
            ) VALUES (?, ?, ?, 0, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                work_seconds = work_seconds + ?,
                overtime_seconds = overtime_seconds + ?,
                overtime_cost = overtime_cost + ?,
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $employeId, $date, $workSeconds, $overtime['daily_overtime_seconds'], 
            $overtime['total_overtime_cost'], $workSeconds, $overtime['daily_overtime_seconds'],
            $overtime['total_overtime_cost']
        ]);
        
        // Mettre à jour les statistiques mensuelles
        $month = date('Y-m', strtotime($date));
        $stmt = $this->pdo->prepare("
            INSERT INTO employee_stats_monthly (
                employe_id, month, total_work_seconds, total_overtime_seconds,
                total_overtime_cost, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_work_seconds = total_work_seconds + ?,
                total_overtime_seconds = total_overtime_seconds + ?,
                total_overtime_cost = total_overtime_cost + ?,
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $employeId, $month, $workSeconds, $overtime['daily_overtime_seconds'],
            $overtime['total_overtime_cost'], $workSeconds, $overtime['daily_overtime_seconds'],
            $overtime['total_overtime_cost']
        ]);
    }

    /**
     * Génère des recommandations basées sur l'analyse
     */
    private function generateRecommendations(int $rawDuration, int $effectiveWork, array $constraints): array {
        $recommendations = [];
        
        // Recommandation sur les pauses
        if ($rawDuration > $this->config['break_policy']['min_work_for_break'] && 
            $effectiveWork > $this->config['break_policy']['min_work_for_break']) {
            $recommendations[] = [
                'type' => 'break',
                'priority' => 'high',
                'message' => 'Pensez à prendre une pause pour maintenir votre productivité',
                'suggestion' => 'Prendre une pause de ' . gmdate('H:i', $this->config['break_policy']['break_duration'])
            ];
        }
        
        // Recommandation sur les heures supplémentaires
        if ($effectiveWork > $this->config['working_hours']['overtime']['threshold']) {
            $overtimeHours = gmdate('H:i', $effectiveWork - $this->config['working_hours']['overtime']['threshold']);
            $recommendations[] = [
                'type' => 'overtime',
                'priority' => 'medium',
                'message' => "Vous avez travaillé {$overtimeHours} heures supplémentaires aujourd'hui",
                'suggestion' => 'Équilibrez votre charge de travail sur la semaine'
            ];
        }
        
        // Alertes sur les contraintes
        foreach ($constraints['violations'] as $violation) {
            $recommendations[] = [
                'type' => 'constraint',
                'priority' => 'critical',
                'message' => $violation['message'],
                'suggestion' => 'Consultez la réglementation du travail'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Formate une durée en secondes en HH:MM:SS
     */
    private function formatDuration(int $seconds): string {
        return gmdate('H:i:s', $seconds);
    }

    /**
     * Formate les informations sur les pauses
     */
    private function formatBreaks(array $breaks): array {
        $formatted = [];
        
        foreach ($breaks as $break) {
            $formatted[] = [
                'start' => $break['start'],
                'end' => $break['end'],
                'duration' => $this->formatDuration($break['duration']),
                'type' => $break['type'],
                'auto_generated' => $break['auto_generated'],
                'description' => $break['description'] ?? ($break['reason'] ?? null)
            ];
        }
        
        return $formatted;
    }

    /**
     * Obtient le temps de travail restant recommandé pour aujourd'hui
     */
    public function getRecommendedRemainingWork(int $employeId, string $date): array {
        $workedToday = $this->getWorkedHoursToday($employeId, $date);
        $dailyTarget = $this->config['working_hours']['default']['daily_hours'];
        $remaining = max(0, $dailyTarget - $workedToday);
        
        return [
            'worked_today' => $this->formatDuration($workedToday),
            'worked_today_seconds' => $workedToday,
            'daily_target' => $this->formatDuration($dailyTarget),
            'remaining' => $this->formatDuration($remaining),
            'remaining_seconds' => $remaining,
            'percentage_complete' => $dailyTarget > 0 ? round(($workedToday / $dailyTarget) * 100, 1) : 0
        ];
    }

    /**
     * Obtient le prochain moment recommandé pour une pause
     */
    public function getNextBreakRecommendation(int $employeId): array {
        $date = date('Y-m-d');
        $continuousWork = $this->getContinuousWorkSinceLastBreak($employeId);
        $needsBreak = $continuousWork >= $this->config['break_policy']['min_work_for_break'];
        
        $nextBreakIn = max(0, $this->config['break_policy']['min_work_for_break'] - $continuousWork);
        $optimalBreakTime = time() + $nextBreakIn;
        
        return [
            'needs_break' => $needsBreak,
            'continuous_work_since_last_break' => $this->formatDuration($continuousWork),
            'continuous_work_seconds' => $continuousWork,
            'next_break_in' => $this->formatDuration($nextBreakIn),
            'next_break_in_seconds' => $nextBreakIn,
            'optimal_break_time' => date('H:i', $optimalBreakTime),
            'break_window' => [
                'start' => $this->config['break_policy']['break_window_start'],
                'end' => $this->config['break_policy']['break_window_end']
            ]
        ];
    }

    /**
     * Obtient le temps de travail continu depuis la dernière pause
     */
    private function getContinuousWorkSinceLastBreak(int $employeId): int {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(fin), NOW()) as last_break_end
            FROM pauses 
            WHERE employe_id = ? 
            AND DATE(debut) = CURDATE()
            AND status = 'valid'
        ");
        
        $stmt->execute([$employeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastBreakEnd = strtotime($result['last_break_end'] ?? date('Y-m-d H:i:s'));
        return time() - $lastBreakEnd;
    }

    /**
     * Obtient le résumé de la semaine de travail
     */
    public function getWeeklySummary(int $employeId): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(debut) as work_date,
                SUM(TIMESTAMPDIFF(SECOND, debut, fin)) as daily_work_seconds,
                COUNT(DISTINCT p.id) as break_count,
                SUM(p.duree) as total_break_seconds
            FROM travail_periods tp
            LEFT JOIN pauses p ON p.employe_id = tp.employe_id AND DATE(p.debut) = DATE(tp.debut)
            WHERE tp.employe_id = ?
            AND YEARWEEK(tp.debut, 1) = YEARWEEK(CURDATE(), 1)
            GROUP BY DATE(tp.debut)
            ORDER BY work_date
        ");
        
        $stmt->execute([$employeId]);
        $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $weeklyTotal = 0;
        $weeklyBreaks = 0;
        $daysWorked = [];
        
        foreach ($dailyStats as $day) {
            $weeklyTotal += (int)$day['daily_work_seconds'];
            $weeklyBreaks += (int)($day['total_break_seconds'] ?? 0);
            
            $daysWorked[] = [
                'date' => $day['work_date'],
                'work_hours' => $this->formatDuration((int)$day['daily_work_seconds']),
                'work_seconds' => (int)$day['daily_work_seconds'],
                'breaks' => (int)$day['break_count'],
                'break_hours' => $this->formatDuration((int)($day['total_break_seconds'] ?? 0)),
                'break_seconds' => (int)($day['total_break_seconds'] ?? 0),
                'effective_hours' => $this->formatDuration(max(0, (int)$day['daily_work_seconds'] - (int)($day['total_break_seconds'] ?? 0)))
            ];
        }
        
        $weeklyTarget = $this->config['working_hours']['default']['weekly_hours'];
        $weeklyRemaining = max(0, $weeklyTarget - $weeklyTotal);
        $weeklyOvertime = max(0, $weeklyTotal - $weeklyTarget);
        
        return [
            'week_number' => date('W'),
            'days_worked' => $daysWorked,
            'totals' => [
                'work_hours' => $this->formatDuration($weeklyTotal),
                'work_seconds' => $weeklyTotal,
                'break_hours' => $this->formatDuration($weeklyBreaks),
                'break_seconds' => $weeklyBreaks,
                'effective_hours' => $this->formatDuration(max(0, $weeklyTotal - $weeklyBreaks))
            ],
            'targets' => [
                'weekly_target' => $this->formatDuration($weeklyTarget),
                'weekly_remaining' => $this->formatDuration($weeklyRemaining),
                'weekly_overtime' => $this->formatDuration($weeklyOvertime),
                'percentage_complete' => $weeklyTarget > 0 ? round(($weeklyTotal / $weeklyTarget) * 100, 1) : 0
            ],
            'recommendations' => $this->generateWeeklyRecommendations($weeklyTotal, $weeklyTarget)
        ];
    }

    /**
     * Génère des recommandations pour la semaine
     */
    private function generateWeeklyRecommendations(int $weeklyTotal, int $weeklyTarget): array {
        $recommendations = [];
        
        $percentage = $weeklyTarget > 0 ? ($weeklyTotal / $weeklyTarget) * 100 : 0;
        
        if ($percentage > 90) {
            $recommendations[] = [
                'type' => 'weekly_completion',
                'priority' => 'low',
                'message' => "Vous avez atteint " . round($percentage, 1) . "% de votre quota hebdomadaire",
                'suggestion' => 'Excellent travail !'
            ];
        } elseif ($percentage < 50) {
            $remainingDays = 5 - date('N') + 1; // Jours restants dans la semaine
            $hoursNeededPerDay = ($weeklyTarget - $weeklyTotal) / $remainingDays / 3600;
            
            $recommendations[] = [
                'type' => 'weekly_completion',
                'priority' => 'medium',
                'message' => "Vous êtes à " . round($percentage, 1) . "% de votre quota hebdomadaire",
                'suggestion' => "Ciblez " . round($hoursNeededPerDay, 1) . "h de travail par jour pour atteindre votre objectif"
            ];
        }
        
        return $recommendations;
    }
}

/**
 * Interface de logger pour la compatibilité
 */
interface LoggerInterface {
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}

/**
 * Logger minimal pour les environnements sans logger configuré
 */
class NullLogger implements LoggerInterface {
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
}