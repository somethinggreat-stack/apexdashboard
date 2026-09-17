@if (session('chat_logout'))
<script src="{{ asset('js/team-chat-privacy.js') }}?v={{ filemtime(public_path('js/team-chat-privacy.js')) }}"></script>
<script>window.ApexChatPrivacy.clear(@json(session('chat_logout')));</script>
@endif
