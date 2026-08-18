@extends('layouts.app')

@section('content')
    {{-- Access Deny dialog: shown when a restricted role (e.g. KS) visits a module
         they are not allowed to use. Redirects back to the SEN Search screen. --}}
    <div class="modal fade" id="accessDeniedModal" tabindex="-1" data-bs-backdrop="static"
         data-bs-keyboard="false" aria-labelledby="accessDeniedTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="accessDeniedTitle">
                        <i class="bi bi-shield-lock me-1" style="color:var(--danger,#dc3545);"></i>Access Deny
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="font-size:.88rem;color:var(--text);">
                    You do not have permission to access this page.
                    <div class="mt-2" style="font-size:.78rem;color:var(--text-faint);">Click OK to return to SEN Search.</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary" id="accessDeniedOk">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var target = '{{ route('admin.sen-search') }}';
            var done = false;
            var go = function () {
                if (done) return;
                done = true;
                window.location.href = target;
            };

            var modalEl = document.getElementById('accessDeniedModal');
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', go, { once: true });
            document.getElementById('accessDeniedOk').addEventListener('click', function () {
                modal.hide();
            });
            modal.show();
        });
    </script>
@endsection
