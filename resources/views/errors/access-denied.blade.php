@extends('layouts.app')

@section('content')
    {{-- Access Deny page (static, no auto-redirect): shown when a restricted
         role (e.g. KS) opens a record/module they are not allowed to use.
         The user navigates back via the menu (e.g. the SEN Search item). --}}
    <div class="container" style="max-width: 560px; margin-top: 14vh; text-align: center;">
        <i class="bi bi-shield-lock" style="font-size: 3.2rem; color: var(--danger, #dc3545);"></i>
        <h1 class="mt-3 mb-2" style="font-weight: 700;">Access Deny</h1>
        <p style="color: var(--text-muted); font-size: .95rem;">
            You do not have permission to Access this page/record.
        </p>
    </div>
@endsection
