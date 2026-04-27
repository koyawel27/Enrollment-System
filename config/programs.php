<?php
/**
 * Program helpers - config/programs.php
 *
 * get_program_label() and get_all_programs() now read from the `programs`
 * DB table instead of a hardcoded array. Falls back to the code itself
 * if the DB is unavailable or the code is not found.
 *
 * $conn must already be available (included after config/db.php).
 */

/**
 * Returns all active programs indexed by code.
 * Shape: [ 'BSIS' => ['code'=>'BSIS','name'=>'...','category'=>'CHED', ...], ... ]
 *
 * Results are cached in a static variable so the DB is only hit once per request.
 *
 * @param  mysqli $conn
 * @return array
 */
function get_all_programs($conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    $q = mysqli_query($conn,
        "SELECT code, name, category, department, department_logo,
                description, careers, alt_jobs, display_order
         FROM programs
         WHERE is_active = 1
         ORDER BY display_order ASC, id ASC"
    );
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            // Decode JSON arrays to PHP arrays for easy iteration
            $row['careers_arr']  = json_decode($row['careers']  ?? '[]', true) ?: [];
            $row['alt_jobs_arr'] = json_decode($row['alt_jobs'] ?? '[]', true) ?: [];
            $cache[$row['code']] = $row;
        }
    }
    return $cache;
}

/**
 * 
 *
 * @param  string|null $code
 * @param  mysqli|null $conn  Pass $conn for DB lookup;
 * @return string
 */
function get_program_label(?string $code, $conn = null): string {
    if (!$code) return '—';

    // Use static cache if already populated
    static $label_cache = [];
    if (isset($label_cache[$code])) return $label_cache[$code];

    if ($conn !== null) {
        $programs = get_all_programs($conn);
        if (isset($programs[$code])) {
            $label_cache[$code] = $programs[$code]['name'];
            return $label_cache[$code];
        }
    }

    // Fallback: return the code itself
    return $code;
}