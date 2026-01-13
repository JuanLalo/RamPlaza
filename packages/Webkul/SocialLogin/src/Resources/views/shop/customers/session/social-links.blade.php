@php
    // Capture intended URL to pass through OAuth flow #191
    // Priority: session intended URL > previous URL (excluding login pages) > home
    $intendedUrl = session('url.intended');
    if (!$intendedUrl) {
        $previousUrl = url()->previous();
        // Don't use login/register pages as intended URL
        if ($previousUrl && !str_contains($previousUrl, '/customer/session') && !str_contains($previousUrl, '/customer/register')) {
            $intendedUrl = $previousUrl;
        }
    }
@endphp

<div class="flex gap-3">
    @foreach(['enable_facebook', 'enable_twitter', 'enable_google', 'enable_linkedin-openid', 'enable_github', 'enable_ram'] as $social)
        @if (! core()->getConfigData('customer.settings.social_login.' . $social))
            @continue
        @endif

        @php
            $icon = explode('_', $social);
            $socialUrl = route('customer.social-login.index', $icon[1]);
            if ($intendedUrl) {
                $socialUrl .= '?redirect=' . urlencode($intendedUrl);
            }
        @endphp

        <a
            href="{{ $socialUrl }}"
            class="transition-all hover:opacity-[0.8]"
            aria-label="{{ $icon[0] }}"
        >
            @include('social_login::icons.' . $icon[1])
        </a>
    @endforeach
</div>