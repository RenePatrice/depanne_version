<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #0F172A; }
        header { border-bottom: 2px solid #1B6FF3; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { font-size: 13pt; margin: 0 0 2px; color: #0B4CB8; }
        .meta { font-size: 7.5pt; color: #64748B; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #EEF3FA; text-align: left; padding: 5px 6px;
            font-size: 7pt; text-transform: uppercase; letter-spacing: .04em; color: #334155;
            border-bottom: 1px solid #C3D0E4;
        }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #E2E8F0; }
        tbody tr:nth-child(even) td { background: #F8FAFC; }
        .avis { margin-top: 10px; font-size: 7.5pt; color: #B45309; }
        footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7pt; color: #94A3B8; }
    </style>
</head>
<body>

<header>
    <h1>{{ $titre }}</h1>
    <div class="meta">
        Dépanne-Moi · Conakry — {{ $total }} ligne{{ $total > 1 ? 's' : '' }} ·
        généré le {{ $genereLe->translatedFormat('j F Y à H:i') }} (heure de Conakry)
    </div>
</header>

<table>
    <thead>
        <tr>
            @foreach ($colonnes as $entete)
                <th>{{ $entete }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($lignes as $ligne)
            <tr>
                @foreach (array_keys($colonnes) as $cle)
                    <td>{{ $ligne[$cle] ?? '—' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

@if ($tronque)
    <p class="avis">
        Ce PDF est limité aux {{ number_format($limite, 0, ',', ' ') }} premières lignes sur
        {{ number_format($total, 0, ',', ' ') }}. Pour l'intégralité, utilise l'export CSV ou Excel.
    </p>
@endif

<footer>Document interne — Dépanne-Moi</footer>

</body>
</html>
