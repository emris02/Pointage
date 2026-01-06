<?php
/**
 * Helpers for formatting time values for the UI
 * - minutes_to_human: display minutes as "X h Y min (Z.ZZ h)" or "N min"
 * - seconds_to_human_hours: display seconds as "HH:MM (D.DD h)"
 * - hhmmss_to_human_hours: convert HH:MM:SS to "HH:MM (D.DD h)"
 */

if (!function_exists('minutes_to_human')) {
    function minutes_to_human(int $minutes): string {
        $minutes = max(0, $minutes);
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        $decimal = round($minutes / 60, 2);
        if ($rem === 0) {
            return sprintf('%d h (%0.2f h)', $hours, $decimal);
        }
        return sprintf('%d h %02d min (%0.2f h)', $hours, $rem, $decimal);
    }
}

if (!function_exists('seconds_to_human_hours')) {
    function seconds_to_human_hours(int $seconds): string {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $decimal = round($seconds / 3600, 2);
        return sprintf('%02d:%02d (%0.2f h)', $hours, $minutes, $decimal);
    }
}

if (!function_exists('hhmmss_to_human_hours')) {
    function hhmmss_to_human_hours(string $hhmmss): string {
        $parts = explode(':', $hhmmss);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        $s = (int)($parts[2] ?? 0);
        $totalSeconds = $h * 3600 + $m * 60 + $s;
        return seconds_to_human_hours($totalSeconds);
    }
}
