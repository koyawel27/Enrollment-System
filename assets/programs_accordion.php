<!-- ============================================
     PROGRAMS OFFERED SECTION
     Replace the entire .program-accordion-block div in index.php
============================================ -->

<?php
// Group programs for accordion rendering
// Departments that group multiple programs under one accordion header
$grouped_departments = ['Information Technology Education', 'Hospitality'];

// Separate grouped vs standalone programs
$dept_groups   = []; // ['dept_name' => [programs...]]
$standalone    = []; // [programs...]

foreach ($all_programs as $p) {
    if (!empty($p['department']) && in_array($p['department'], $grouped_departments)) {
        $dept_groups[$p['department']][] = $p;
    } else {
        $standalone[] = $p;
    }
}

// Helper: render a single program card (logo left, name+desc right, careers below)
function render_program_card(array $p, bool $use_dept_logo = false): void {
    $logo_file = !empty($p['department_logo']) ? basename($p['department_logo']) : null;
    ?>
    <div class="prog-card">
        <div class="prog-card-top">
            <?php if ($logo_file): ?>
            <div class="prog-card-logo">
                <img src="uploads/dept_logos/<?php echo htmlspecialchars($logo_file); ?>"
                     alt="<?php echo htmlspecialchars($p['name']); ?> logo">
            </div>
            <?php endif; ?>
            <div class="prog-card-info">
                <h4 class="prog-card-name"><?php echo htmlspecialchars($p['name']); ?></h4>
                <?php if (!empty($p['description'])): ?>
                <p class="prog-card-desc"><?php echo htmlspecialchars($p['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($p['careers_arr']) || !empty($p['alt_jobs_arr'])): ?>
        <div class="prog-card-bottom">
            <?php if (!empty($p['careers_arr'])): ?>
            <div class="prog-card-col">
                <h5>Careers and Opportunities</h5>
                <ul>
                    <?php foreach ($p['careers_arr'] as $c): ?>
                    <li><?php echo htmlspecialchars($c); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($p['alt_jobs_arr'])): ?>
            <div class="prog-card-col">
                <h5>Alternative Jobs</h5>
                <ul>
                    <?php foreach ($p['alt_jobs_arr'] as $j): ?>
                    <li><?php echo htmlspecialchars($j); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<div class="program-accordion-block">

    <!-- CHED GROUP HEADER -->
    <h3 class="program-track-heading">CHED Programs</h3>

    <?php
    // Render CHED programs
    foreach ([$dept_groups, $standalone] as $source_key => $source):
        if ($source_key === 0):
            // Grouped departments (ITE, etc.) — CHED only
            foreach ($dept_groups as $dept_name => $dept_programs):
                // Only render CHED groups here
                if ($dept_programs[0]['category'] !== 'CHED') continue;
    ?>
    <!-- GROUPED: <?php echo htmlspecialchars($dept_name); ?> -->
    <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($dept_name)); ?>">
        <button type="button" class="program-accordion-head program-accordion-head--group"
                onclick="toggleProgramAccordion(this)" aria-expanded="false">
            <span class="program-accordion-title program-accordion-title--group">
                <?php echo htmlspecialchars($dept_name); ?>
            </span>
            <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <div class="program-accordion-body">
            <div class="program-accordion-body-inner">
                <?php foreach ($dept_programs as $p): ?>
                <?php render_program_card($p); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
            endforeach;
        else:
            // Standalone CHED programs
            foreach ($standalone as $p):
                if ($p['category'] !== 'CHED') continue;
    ?>
    <!-- STANDALONE CHED: <?php echo htmlspecialchars($p['code']); ?> -->
    <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
        <button type="button" class="program-accordion-head program-accordion-head--single"
                onclick="toggleProgramAccordion(this)" aria-expanded="false">
            <span class="program-accordion-title program-accordion-title--single">
                <?php echo htmlspecialchars($p['name']); ?>
            </span>
            <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <div class="program-accordion-body">
            <div class="program-accordion-body-inner">
                <?php render_program_card($p); ?>
            </div>
        </div>
    </div>
    <?php
            endforeach;
        endif;
    endforeach;
    ?>

    <!-- TESDA GROUP HEADER -->
    <h3 class="program-track-heading" style="margin-top:2rem;">TECHVOC Programs</h3>

    <?php
    // Render TESDA programs
    foreach ([$dept_groups, $standalone] as $source_key => $source):
        if ($source_key === 0):
            foreach ($dept_groups as $dept_name => $dept_programs):
                if ($dept_programs[0]['category'] !== 'TESDA') continue;
    ?>
    <!-- GROUPED TESDA: <?php echo htmlspecialchars($dept_name); ?> -->
    <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($dept_name)); ?>">
        <button type="button" class="program-accordion-head program-accordion-head--group"
                onclick="toggleProgramAccordion(this)" aria-expanded="false">
            <span class="program-accordion-title program-accordion-title--group">
                <?php echo htmlspecialchars($dept_name); ?>
            </span>
            <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <div class="program-accordion-body">
            <div class="program-accordion-body-inner">
                <?php foreach ($dept_programs as $p): ?>
                <?php render_program_card($p); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
            endforeach;
        else:
            foreach ($standalone as $p):
                if ($p['category'] !== 'TESDA') continue;
    ?>
    <!-- STANDALONE TESDA: <?php echo htmlspecialchars($p['code']); ?> -->
    <div class="program-accordion-item" data-program-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
        <button type="button" class="program-accordion-head program-accordion-head--single"
                onclick="toggleProgramAccordion(this)" aria-expanded="false">
            <span class="program-accordion-title program-accordion-title--single">
                <?php echo htmlspecialchars($p['name']); ?>
            </span>
            <svg class="program-accordion-chevron" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <div class="program-accordion-body">
            <div class="program-accordion-body-inner">
                <?php render_program_card($p); ?>
            </div>
        </div>
    </div>
    <?php
            endforeach;
        endif;
    endforeach;
    ?>

</div><!-- /.program-accordion-block -->

<!-- ============================================
     ADD THESE CSS RULES to index.css
     (or inside a <style> block in index.php)
============================================ -->
<!--
.program-track-heading {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--bpc-green-dark);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 1.5rem 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 3px solid var(--bpc-gold);
}

/* Accordion head — grouped department */
.program-accordion-head--group {
    background: #f0f7f0;
}
.program-accordion-head--group:hover {
    background: #e0f0e0;
}
.program-accordion-title--group {
    font-size: 1.05rem;
    font-weight: 700;
    color: #004d00;
}

/* Accordion head — single program */
.program-accordion-title--single {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-dark);
}

/* Program card layout */
.prog-card {
    background: white;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
}
.prog-card:last-child { margin-bottom: 0; }

.prog-card-top {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.prog-card-logo {
    flex-shrink: 0;
}
.prog-card-logo img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 6px;
    background: #fafafa;
}
.prog-card-info {
    flex: 1;
}
.prog-card-name {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--bpc-green-dark);
    margin-bottom: 0.4rem;
    line-height: 1.3;
}
.prog-card-desc {
    font-size: 0.875rem;
    color: #555;
    line-height: 1.6;
    margin: 0;
}

.prog-card-bottom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #f0f0f0;
}
.prog-card-col h5 {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--bpc-green);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.prog-card-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.prog-card-col ul li {
    font-size: 0.875rem;
    color: #444;
    padding: 0.2rem 0;
    padding-left: 1rem;
    position: relative;
}
.prog-card-col ul li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: var(--bpc-green);
    font-weight: 700;
}

@media (max-width: 600px) {
    .prog-card-top  { flex-direction: column; }
    .prog-card-logo img { width: 60px; height: 60px; }
    .prog-card-bottom { grid-template-columns: 1fr; gap: 1rem; }
}
-->

