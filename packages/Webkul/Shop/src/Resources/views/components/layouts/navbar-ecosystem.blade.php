{{--
    RAM Ecosystem Navigation - Mobile Bottom Navbar
    Cross-app navigation: #192

    Uses the shared Web Component from RAM for consistent navigation
    across the entire ecosystem (RAM, Plaza, future apps).

    Script loaded in main layout (index.blade.php)
--}}
@php
    $ramBaseUrl = config('services.ram.base_url', 'https://redactivamexico.net');
@endphp

{{-- Mobile variant (bottom navbar, <=768px) --}}
<ram-ecosystem-nav
    variant="mobile"
    active="plaza"
    base-url="{{ $ramBaseUrl }}"
    plaza-url="{{ url('/') }}"
></ram-ecosystem-nav>
