@extends('layouts.app')

@section('content')
    {{-- Access Deny page (static, no auto-redirect): shown when a restricted
         role (e.g. KS) opens a record/module they are not allowed to use.
         The user navigates back via the menu (e.g. the SEN Search item). --}}
    <div class="container u-maxw-560 u-mt-14vh u-tac">
        <i class="bi bi-shield-lock u-fs-320 u-c-danger"></i>
        <h1 class="mt-3 mb-2 u-fw-700">Access Deny</h1>
        <p class="u-c-text-muted u-fs-095">
            You do not have permission to Access this page/record.
        </p>
    </div>
@endsection
