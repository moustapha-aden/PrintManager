<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Production Imprimantes</title>
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
        <p>Période : {{ $startDate }} - {{ $endDate }}</p>
        @if($company)
            <p>Société : {{ $company->name }}</p>
        @endif
        @if($department)
            <p>Département : {{ $department->name }}</p>
        @endif
        <p>Généré le : {{ date('d/m/Y') }}</p>
    </div>

    @forelse($quotas as $quota)
        <div class="section">
            <h2>Imprimante : {{ $quota->printer?->brand ?? 'N/A' }} {{ $quota->printer?->model ?? '' }} ({{ $quota->printer?->serial ?? '' }})</h2>
            <ul class="details-list">
                <li><strong>Marque :</strong> {{ $quota->printer?->brand ?? 'N/A' }}</li>
                <li><strong>Modèle :</strong> {{ $quota->printer?->model ?? 'N/A' }}</li>
                <li><strong>Numéro de série :</strong> {{ $quota->printer?->serial ?? 'N/A' }}</li>
                <li><strong>Total de quota :</strong> {{ $quota->printer?->total_quota_pages ?? 'N/A' }}</li>
            </ul>

            <h3>Relevés de Quotas</h3>
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
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($quota->mois)->format('d/m/Y') }}</td>
                        <td>{{ $quota->monthly_quota_bw }}</td>
                        <td>{{ $quota->monthly_quota_color }}</td>
                        <td>{{ $quota->total_quota }}</td>
                        <td>{{ $quota->depassementBW ?? 0 }}</td>
                        <td>{{ $quota->depassementColor ?? 0 }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p>Aucun quota trouvé pour cette période.</p>
    @endforelse

</body>
</html>
