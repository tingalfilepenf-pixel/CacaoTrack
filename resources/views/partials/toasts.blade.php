@php
    // Transient toast notifications (PRD §19 feedback). These are session
    // flashes only — no database table is involved (PRD §9 / RULE 4).
    $toastMessages = [];
    if (session('success')) {
        $toastMessages[] = ['type' => 'success', 'icon' => 'check-circle', 'message' => session('success')];
    }
    if (session('error')) {
        $toastMessages[] = ['type' => 'danger', 'icon' => 'exclamation-triangle', 'message' => session('error')];
    }
    if (session('warning')) {
        $toastMessages[] = ['type' => 'warning', 'icon' => 'exclamation-triangle-fill', 'message' => session('warning')];
    }
@endphp

@if (! empty($toastMessages))
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
        @foreach ($toastMessages as $toast)
            <div class="toast align-items-center text-bg-{{ $toast['type'] }} border-0" role="alert"
                aria-live="assertive" aria-atomic="true" data-autoshow>
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-{{ $toast['icon'] }} me-2"></i>{{ $toast['message'] }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        @endforeach
    </div>
@endif
