{{-- Shared role guide (description + Can/Cannot lists) shown under the role
     field when a template is chosen. Every value comes from Role.php (RULE 6). --}}
<div class="border rounded bg-light p-3 mt-2 d-none" id="{{ $prefix }}RoleGuide" aria-live="polite">
    <div class="fw-semibold small" id="{{ $prefix }}RoleGuideTitle"></div>
    <div class="text-muted small mb-2" id="{{ $prefix }}RoleGuideDescription"></div>
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <div class="text-success small fw-bold text-uppercase mb-1">Can</div>
            <ul class="list-unstyled mb-0 small" id="{{ $prefix }}RoleGuideCan"></ul>
        </div>
        <div class="col-12 col-md-6">
            <div class="text-muted small fw-bold text-uppercase mb-1">Cannot</div>
            <ul class="list-unstyled mb-0 small text-muted" id="{{ $prefix }}RoleGuideCannot"></ul>
        </div>
    </div>
</div>
