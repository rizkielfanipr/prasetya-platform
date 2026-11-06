<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Simulasi pembayaran</title>
        <style>
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; box-sizing: border-box; background: #f6f3f0; color: #0f172a; font-family: system-ui, sans-serif; }
            main { width: min(440px, 100%); padding: 2rem; box-sizing: border-box; background: #fff; border: 2px dashed #b45309; border-radius: 16px; }
            h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
            p { margin: 0 0 1rem; line-height: 1.5; }
            .note { padding: .6rem .8rem; color: #92400e; background: #fef3c7; border-radius: 8px; font-size: .875rem; font-weight: 600; }
            dl { margin: 0 0 1.25rem; display: grid; grid-template-columns: auto 1fr; gap: .4rem 1rem; }
            dt { color: #646464; }
            dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
            button, a { width: 100%; min-height: 46px; margin-bottom: .6rem; display: flex; align-items: center; justify-content: center; box-sizing: border-box; border: 1px solid #ded6d0; border-radius: 10px; color: #0f172a; background: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
            .pay { color: #fff; background: #15803d; border-color: #15803d; }
        </style>
    </head>
    <body>
        <main>
            <p class="note">SIMULASI: ini bukan Duitku dan tidak ada uang yang berpindah.</p>
            <h1>Pembayaran {{ $payment->gateway_invoice_number }}</h1>
            <dl>
                <dt>Jumlah</dt>
                <dd>Rp{{ number_format($payment->amount, 0, ',', '.') }}</dd>
                <dt>Status</dt>
                <dd>{{ $payment->status->value }}</dd>
            </dl>
            <form method="post" action="{{ route('dev.fake-checkout.pay', $payment) }}">
                @csrf
                <button class="pay" name="status" value="SUCCESS">Bayar berhasil</button>
                <button name="status" value="EXPIRED">Simulasikan kedaluwarsa</button>
            </form>
            <a href="{{ $returnUrl }}">Kembali ke situs tanpa membayar</a>
        </main>
    </body>
</html>
