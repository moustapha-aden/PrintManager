<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Production Imprimantes</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .section h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .details-list { list-style: none; padding: 0; margin: 0; }
        .details-list li { margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    @php
        \Carbon\Carbon::setLocale('fr');
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');
    @endphp

    <div class="header">
        <h1>Rapport de Production</h1>
        <p>Période : {{ $startDate }} - {{ $endDate }}</p>
        @if($company)
            <p>Société : {{ $company->name }}</p>
        @endif
        <p>Généré le : {{ date('d/m/Y') }}</p>
    </div>

    @forelse($quotas as $quota)
        <div class="section">
            <h2>
                Imprimante : {{ $quota->printer?->brand ?? 'N/A' }}
                {{ $quota->printer?->model ?? '' }}
                ({{ $quota->printer?->serial ?? '' }})
            </h2>

            <table>
                    <thead>
                        <tr>
                            <th>Département</th>
                            <th>Marque</th>
                            <th>Modèle</th>
                            <th>Numéro de série</th>
                            <th>Total de quota</th>
                            <th>Quota</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $quota->printer?->department?->name ?? 'N/A' }}</td>
                            <td>{{ $quota->printer?->brand ?? 'N/A' }}</td>
                            <td>{{ $quota->printer?->model ?? 'N/A' }}</td>
                            <td>{{ $quota->printer?->serial ?? 'N/A' }}</td>
                            <td>{{ $quota->total_quota ?? 0 }}</td>
                            <td>
                                {{
                                    ($quota->printer?->company?->quota_monthly ?? 0) > 1
                                        ? $quota->printer->company->quota_monthly
                                        : (($quota->printer?->department?->quota_monthly ?? 0) > 1
                                            ? $quota->printer->department->quota_monthly
                                            : 0)
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>


            <h3>Relevés du Mois {{ \Carbon\Carbon::parse($quota->mois)->translatedFormat('F Y') }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Quota N&B</th>
                        <th>Quota Couleur</th>
                        <th>Total</th>
                        <th>Dépassement N&B</th>
                        <th>Dépassement Couleur</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
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
