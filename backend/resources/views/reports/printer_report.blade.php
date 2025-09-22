<!DOCTYPE html>
<html>
<head>
    <title>Rapport de Production Imprimante</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .section { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .section h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .details-list { list-style: none; padding: 0; margin: 0; }
        .details-list li { margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
    <link rel="icon" type="image/svg+xml" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
    <rect x="6" y="14" width="12" height="8"/></svg>' />
</head>
<body>

    <div class="header">
        <h1>Rapport de Production</h1>
        <p>Imprimante : {{ $printer->brand }} {{ $printer->model }} ({{ $printer->serial }})</p>
        <p>Généré le : {{ date('d/m/Y') }}</p>
    </div>

    <div class="section">
        <h2>Détails de l'Imprimante</h2>
        <ul class="details-list">
            <li><strong>Marque :</strong> {{ $printer->brand }}</li>
            <li><strong>Modèle :</strong> {{ $printer->model }}</li>
            <li><strong>Numéro de série :</strong> {{ $printer->serial }}</li>
            <li><strong>Société :</strong> {{ $printer->company->name ?? 'N/A' }}</li>
            <li><strong>Département :</strong> {{ $printer->department->name ?? 'N/A' }}</li>
        </ul>
    </div>

    <div class="section">
        <h2>Relevés de Quotas</h2>
        @if($printer->quotas->isEmpty())
            <p>Aucun relevé de quota n'est disponible pour cette imprimante.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Quota N&B</th>
                        <th>Quota Couleur</th>
                        <th>Total</th>
                        <th>Dépassement N&B</th>
                        <th>Dépassement Couleur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($printer->quotas as $quota)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($quota->date_prelevement)->format('d/m/Y') }}</td>
                        <td>{{ $quota->monthly_quota_bw }}</td>
                        <td>{{ $quota->monthly_quota_color }}</td>
                        <td>{{ $quota->total_quota }}</td>
                        <td>{{ $quota->depassementBW }}</td>
                        <td>{{ $quota->depassementColor }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</body>
</html>
