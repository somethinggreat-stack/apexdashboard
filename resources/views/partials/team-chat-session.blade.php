@once
{{-- Shared-PC session lifetime for the chat (see app/Http/Middleware/TeamChatSession.php). --}}
<script src="{{ asset('js/team-chat-privacy.js') }}?v={{ filemtime(public_path('js/team-chat-privacy.js')) }}"></script>
<script>
if (window.ApexChatPrivacy) window.ApexChatPrivacy.start({
    uid: @json((string) Auth::guard('admin')->id()),
    stamp: @json(\App\Http\Middleware\TeamChatSession::stamp(request())),
    login: @json(route('admin.chat-login'))
});
</script>
@endonce
