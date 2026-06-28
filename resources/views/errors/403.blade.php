<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 – Akses Ditolak</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; background: #f1f5f9; min-height: 100dvh; display: flex; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 1.5rem; padding: 2.5rem 2rem; text-align: center; max-width: 360px; width: 100%; margin: 1rem; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .icon-wrap { width: 72px; height: 72px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; }
        .icon-wrap svg { width: 32px; height: 32px; color: #ef4444; }
        .code { font-size: 3rem; font-weight: 800; color: #1e293b; line-height: 1; }
        .title { font-size: 1.125rem; font-weight: 600; color: #1e293b; margin-top: 0.5rem; }
        .desc { font-size: 0.875rem; color: #64748b; margin-top: 0.5rem; line-height: 1.5; }
        .btn { display: inline-block; margin-top: 1.75rem; padding: 0.625rem 1.5rem; background: #10b981; color: white; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: background 0.15s; }
        .btn:hover { background: #059669; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>
        <div class="code">403</div>
        <div class="title">Akses Ditolak</div>
        <p class="desc">Kamu tidak memiliki izin untuk mengakses halaman ini. Hubungi administrator jika ini adalah kesalahan.</p>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="btn">Kembali</a>
    </div>
</body>
</html>
