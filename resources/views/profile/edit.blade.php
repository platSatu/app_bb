@extends('layouts.dashboard')

@section('title', 'My Account')

@section('content')
    @include('profile._tabs', ['active' => 'profile'])

    @include('profile.tabs.profile')

    <script>
        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var icon = button.querySelector('i');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('ri-eye-line', show);
                icon.classList.toggle('ri-eye-off-line', !show);
            });
        });
    </script>
@endsection
