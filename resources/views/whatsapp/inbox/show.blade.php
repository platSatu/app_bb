@extends('layouts.dashboard')

@section('title', 'Inbox · '.$device->name)
@section('page-section', 'WhatsApp')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wa-inbox.css') }}">
@endpush

@section('content')
    @php
        $c = '__CHAT__';
        $urls = [
            'chats' => route('whatsapp.inbox.chats', $device),
            'chat' => route('whatsapp.inbox.chat', [$device, $c]),
            'messages' => route('whatsapp.inbox.messages', [$device, $c]),
            'read' => route('whatsapp.inbox.read', [$device, $c]),
            'presence' => route('whatsapp.inbox.presence', [$device, $c]),
            'detail' => route('whatsapp.inbox.detail', [$device, $c]),
            'mediaList' => route('whatsapp.inbox.media-list', [$device, $c]),
            'labelAttach' => route('whatsapp.inbox.labels.attach', [$device, $c, '__ID__']),
            'notes' => route('whatsapp.inbox.notes.store', [$device, $c]),
            'note' => route('whatsapp.inbox.notes.update', [$device, $c, '__ID__']),
            'assign' => route('whatsapp.inbox.assign', [$device, $c]),
            'bot' => route('whatsapp.inbox.bot', [$device, $c]),
            'contact' => route('whatsapp.inbox.contact', [$device, $c]),
            'vcard' => route('whatsapp.inbox.vcard', [$device, $c]),
            'avatar' => route('whatsapp.inbox.avatar', [$device, $c]),
            'media' => route('whatsapp.inbox.media', [$device, '__ID__']),
            'poll' => route('whatsapp.inbox.poll', [$device, '__ID__']),
            'send' => route('whatsapp.inbox.send', $device),
            'sendMedia' => route('whatsapp.inbox.send-media', $device),
            'sendPoll' => route('whatsapp.inbox.send-poll', $device),
            'realtime' => route('whatsapp.inbox.realtime', $device),
            'quickReplies' => route('whatsapp.inbox.quick-replies', $device),
            'labelsPage' => route('whatsapp.labels.index', ['branch' => $device->branch_id]),
            'quickRepliesPage' => route('whatsapp.quick-replies.index', ['branch' => $device->branch_id]),
        ];
    @endphp

    <div class="card wa-inbox-card" id="wa-inbox"
        data-device="{{ $device->id }}"
        data-status="{{ $device->status }}"
        data-urls='@json($urls)'>
        <div class="wa-device-banner" id="wa-device-banner"><i class="ri-error-warning-line"></i><span></span></div>
        <div class="wa-inbox-shell">
            @include('whatsapp.inbox._list')
            @include('whatsapp.inbox._thread')
            @include('whatsapp.inbox._detail')
        </div>
    </div>

    @include('whatsapp.inbox._modals')
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
    <script src="{{ asset('js/wa-inbox.js') }}"></script>
@endpush
