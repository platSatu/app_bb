@if (config('services.turnstile.site_key'))
    <div class="mb-4">
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-size="flexible"></div>
        @error('cf-turnstile-response')
            <div class="text-danger fs-13 mt-1">{{ $message }}</div>
        @enderror
    </div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
