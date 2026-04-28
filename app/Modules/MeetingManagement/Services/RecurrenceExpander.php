<?php

namespace App\Modules\MeetingManagement\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RecurrenceExpander
{
    /**
     * Generate occurrence start datetimes for a simple recurrence rule.
     * Supports: none, daily, weekly, biweekly, monthly. The first occurrence
     * is the seed itself; the last is bounded by $until (inclusive) or 26
     * occurrences, whichever comes first.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function expand(CarbonInterface $seed, ?string $rule, ?CarbonInterface $until = null, int $cap = 26): array
    {
        $start = CarbonImmutable::parse($seed);
        if (! $rule || $rule === 'none') {
            return [$start];
        }

        $until = $until ? CarbonImmutable::parse($until) : null;
        $occurrences = [$start];

        $next = $start;
        while (count($occurrences) < $cap) {
            $next = match ($rule) {
                'daily' => $next->addDay(),
                'weekly' => $next->addWeek(),
                'biweekly' => $next->addWeeks(2),
                'monthly' => $next->addMonthNoOverflow(),
                default => null,
            };

            if (! $next) {
                break;
            }

            if ($until && $next->greaterThan($until)) {
                break;
            }

            $occurrences[] = $next;
        }

        return $occurrences;
    }
}
