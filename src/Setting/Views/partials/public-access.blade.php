{{--
    The "Make files public" panel of one remote storage — the app and the mobile app load images and audio straight
    from the bucket, so the bucket has to let anyone read them.
    $panel      the settings tab it belongs to
    $disk       the Laravel disk the button applies to
    $activeTab  the tab that is open
--}}
<form method="POST" action="{{ route('admin.settings.storage.public-read') }}" class="panel settings-form" data-settings-panel="{{ $panel }}" @unless($activeTab === $panel) hidden @endunless>
    @csrf
    <input type="hidden" name="disk" value="{{ $disk }}">

    <div class="form-section">
        <h3>Public access</h3>
        <p>Images and audio are loaded straight from the bucket, so anyone must be able to read them without signing in. If they show as broken links (HTTP 403), click <b>Make files public</b>: it lets the bucket's image and audio folders be read — and nothing else in the bucket — then checks that it worked. The access key needs permission to change bucket policies; saving the connection also runs this check.</p>

        @if(session('public_read_warning.disk') === $disk)
            <div class="inline-warning" style="margin:0 0 16px">{{ session('public_read_warning.message') }}</div>
        @endif
    </div>

    <div class="form-actions"><span></span><button type="submit" class="secondary-button">Make files public</button></div>
</form>
