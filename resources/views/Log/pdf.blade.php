
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Logs ANAGRASC du {{ now()->format('d/m/Y') }}</h2>
    <table>
        <thead>
            <tr>
                <th>Horodatage</th>
                <th>Action</th>
                <th>Utilisateur</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
            <tr>
                <td>{{ $log->horodatage->format('d/m/Y H:i:s') }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->user?->nom_complet ?? 'Système' }}</td>
                <td>{{ $log->description }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
