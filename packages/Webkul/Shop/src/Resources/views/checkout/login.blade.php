{{-- RAM Plaza - Checkout Login #191 --}}
{{-- Direct link to RAM OAuth instead of email/password modal --}}

@php
    $checkoutUrl = route('shop.checkout.onepage.index');
    $loginUrl = route('customer.social-login.index', 'ram') . '?redirect=' . urlencode($checkoutUrl);
@endphp

<div class="flex items-center">
    <a
        href="{{ $loginUrl }}"
        class="text-base font-medium text-blue-700 hover:underline"
    >
        @lang('shop::app.checkout.login.title')
    </a>
</div>
