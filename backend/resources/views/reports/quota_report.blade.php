<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Production Imprimantes</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .highlight-red { color: red; font-weight: bold; }
        .highlight-green { color: green; font-weight: bold; }
        .summary { background: #f9f9f9; border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin-top: 30px; }
        .summary h2 { text-align: center; margin-bottom: 10px; font-size: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport de Production</h1>
        <p>Période : {{ $startDate }} - {{ $endDate }}</p>
        @if($company)
            <p>Société : {{ $company->name }}</p>
        @endif
        <p>Généré le : {{ date('d/m/Y') }}</p>
    </div>

    <div class="summary" style="margin-bottom: 30px; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
        <h2>Résumé Global</h2>
        <table>
            <tr><th>Total Copies N&B</th><td class="{{ ($totalDepassementBW ?? 0) > 0 ? 'highlight-red' : 'highlight-green' }}">{{ $totalDepassementBW }}</td></tr>
            <tr><th>Total Copies Couleur</th><td class="{{ ($totalDepassementColor ?? 0) > 0 ? 'highlight-red' : 'highlight-green' }}">{{ $totalDepassementColor }}</td></tr>
            <tr><th>Total Imprimantes</th><td>{{ $totalPrinters }}</td></tr>
        </table>
    @forelse($quotas as $quota)
        <div class="section">
            <h2>Imprimante : {{ $quota->printer?->brand }} {{ $quota->printer?->model }} ({{ $quota->printer?->serial }})</h2>
            <table>
                <tr><th>Département</th><td>{{ $quota->printer?->department?->name ?? 'N/A' }}</td></tr>
                <tr><th>Total copies</th><td>{{ $quota->total_quota }}</td></tr>
                <tr><th>Dépassement N&B</th>
                    <td class="{{ ($quota->depassementBW ?? 0) > 0 ? 'highlight-red' : 'highlight-green' }}">
                        {{ $quota->depassementBW ?? 0 }}
                    </td>
                </tr>
                <tr><th>Dépassement Couleur</th>
                    <td class="{{ ($quota->depassementColor ?? 0) > 0 ? 'highlight-red' : 'highlight-green' }}">
                        {{ $quota->depassementColor ?? 0 }}
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <p>Aucun quota trouvé pour cette période.</p>
    @endforelse


</body>
</html>
