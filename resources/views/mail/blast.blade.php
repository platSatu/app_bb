{{-- Tampilan Email Blast & email Agent Prospek (EmailBlastService::html). Tabel + style inline supaya rapi di Gmail,
     Outlook & HP. Isi berasal dari teks biasa: $blocks = [['heading', teks], ['text', teks], ['button', teks, url], ['image', url]]. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject }}</title>
    <style>
        @media (max-width: 620px) { .card { padding: 24px 20px !important; } .heading { font-size: 22px !important; } }
        a { color: #e36666; }
    </style>
</head>
<body style="margin:0; padding:0; background:#f4f5f7; -webkit-text-size-adjust:100%">
    {{-- Teks pratinjau di daftar inbox (tidak terlihat di isi email). --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7">
        <tr>
            <td align="center" style="padding:24px 12px">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">
                    <tr>
                        <td align="center" style="padding:4px 0 20px">
                            {{-- Logo dari Pengaturan Website (superadmin); belum diisi = nama pengirim. --}}
                            @if ($logo)
                                <img src="{{ $logo }}" width="140" alt="{{ $brand }}" style="display:block; width:140px; max-width:140px; height:auto; border:0; font:bold 22px Arial, sans-serif; color:#e36666">
                            @else
                                <span style="font:bold 24px Arial, sans-serif; color:#e36666; letter-spacing:.3px">{{ $brand }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="card" style="background:#ffffff; border-radius:14px; padding:36px 40px; border-top:5px solid #e36666; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.65; color:#2b2f36">
                            @foreach ($blocks as $block)
                                @if ($block[0] === 'heading')
                                    <h1 class="heading" style="margin:0 0 16px; font-size:26px; line-height:1.3; color:#1f2329; font-weight:bold">{{ $block[1] }}</h1>
                                @elseif ($block[0] === 'image')
                                    <img src="{{ $block[1] }}" alt="" width="520" style="display:block; width:100%; max-width:520px; height:auto; border:0; border-radius:10px; margin:0 0 18px">
                                @elseif ($block[0] === 'button')
                                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 22px">
                                        <tr>
                                            <td align="center" bgcolor="#e36666" style="border-radius:8px">
                                                <a href="{{ $block[2] }}" target="_blank" rel="noopener" style="display:inline-block; padding:13px 28px; font:bold 15px Arial, sans-serif; color:#ffffff; text-decoration:none; border-radius:8px">{{ $block[1] }}</a>
                                            </td>
                                        </tr>
                                    </table>
                                @else
                                    <p style="margin:0 0 16px">{!! $linkify(nl2br(e($block[1]))) !!}</p>
                                @endif
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 16px; font-family:Arial, sans-serif; font-size:12px; line-height:1.6; color:#8a919c">
                            {{ $brand }}
                            @if ($footer) <br>{!! $linkify(nl2br(e($footer))) !!} @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
