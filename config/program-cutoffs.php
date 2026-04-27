<?php
/**
 * Per-program exam cutoffs and assignment logic (CHED).
 * config/program-cutoffs.php
 */

$CHED_PROGRAM_CUTOFFS = [
    'BSIS'   => 85,
    'BSAIS'  => 85,
    'BSOM'   => 80,
    'BSCA'   => 80,
    'ACT'    => 75,
    'BTVTED' => 75,
];

/**
 * Determine the best qualified program based on exam score and choices.
 *
 * Return shape:
 *   assigned_program : string|null  — program code to assign (null if none yet)
 *   final_category   : string       — 'CHED' or 'TESDA'
 *   final_status     : string       — new applications.status value
 *   offered_program  : string|null  — TESDA program being offered (for decision flow)
 *
 * TESDA fallback (third_choice) no longer auto-assigns.
 * It returns 'Awaiting Applicant Decision' so the student can accept or decline.
 */
function determine_program_assignment(array $app_row, int $score, array $cutoffs): array {
    $first    = $app_row['first_choice']      ?? '';
    $second   = $app_row['second_choice']     ?? '';
    $third    = $app_row['third_choice']      ?? '';
    $category = $app_row['program_category']  ?? 'CHED';

    // Pure TESDA applicant — no exam, already handled upstream
    if ($category === 'TESDA') {
        return [
            'assigned_program' => $first ?: null,
            'final_category'   => 'TESDA',
            'final_status'     => 'Documents Verified',
            'offered_program'  => null,
        ];
    }

    $is_ched = static function (?string $code) use ($cutoffs): bool {
        return $code && array_key_exists($code, $cutoffs);
    };

    // Check 1st choice
    if ($is_ched($first) && $score >= (int)$cutoffs[$first]) {
        return [
            'assigned_program' => $first,
            'final_category'   => 'CHED',
            'final_status'     => 'Exam Completed',
            'offered_program'  => null,
        ];
    }

    // Check 2nd choice
    if ($is_ched($second) && $score >= (int)$cutoffs[$second]) {
        return [
            'assigned_program' => $second,
            'final_category'   => 'CHED',
            'final_status'     => 'Exam Completed',
            'offered_program'  => null,
        ];
    }

    // Check 3rd choice — TESDA fallback
    // Do NOT auto-assign. Ask the student first.
    if ($third && !$is_ched($third)) {
        return [
            'assigned_program' => null,       // not assigned yet — student decides
            'final_category'   => 'CHED',     // stays CHED until student accepts
            'final_status'     => 'Awaiting Applicant Decision',
            'offered_program'  => $third,     // the TESDA program being offered
        ];
    }

    // No qualifying program at all
    return [
        'assigned_program' => null,
        'final_category'   => 'CHED',
        'final_status'     => 'Exam Failed',
        'offered_program'  => null,
    ];
}