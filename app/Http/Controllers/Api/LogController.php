<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
class LogController extends Controller
{
    /**
     * GET /api/logs
     * auth:sanctum + role:administrateur
     * Liste paginée et filtrable des logs de traçabilité .
     */
    public function index(Request $request)
    {
        // On commence la requête avec le nom de l'utilisateur
        $query = AppLog::with('user:id,nom,prenom')->orderByDesc('horodatage');

        // Filtre par type d'action (?action=connexion)
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filtre par utilisateur (?user_id=5)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtre par date de début (?date_debut=2026-01-01)
        if ($request->filled('date_debut')) {
            $query->whereDate('horodatage', '>=', $request->date_debut);
        }

        // Filtre par date de fin (?date_fin=2026-12-31)
        if ($request->filled('date_fin')) {
            $query->whereDate('horodatage', '<=', $request->date_fin);
        }

        // Filtre par mot-clé dans la description (?search=connexion)
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate(25);

        return response()->json($logs);
    }

    /**
 * GET /api/logs/export
 * auth:sanctum + role:administrateur
 * Export des logs filtrés en PDF.
 */
public function export(Request $request)
{
    // Récupérer les logs avec les mêmes filtres que index()
    $query = AppLog::with('user:id,nom,prenom')->orderByDesc('horodatage');

    if ($request->filled('action')) {
        $query->where('action', $request->action);
    }
    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }
    if ($request->filled('date_debut')) {
        $query->whereDate('horodatage', '>=', $request->date_debut);
    }
    if ($request->filled('date_fin')) {
        $query->whereDate('horodatage', '<=', $request->date_fin);
    }
    if ($request->filled('search')) {
        $query->where('description', 'like', '%' . $request->search . '%');
    }

    $logs = $query->limit(1000)->get();

    // Générer le PDF avec une vue Blade
    $pdf = Pdf::loadView('Log.pdf', compact('logs'));

    // Télécharger le fichier
    return $pdf->download('logs_anagrasc.pdf');
}

    /**
 * Enregistre un log depuis n'importe quel contrôleur.
 * Utilisation : LogController::log('action', 'description');
 *
 * @param string $action      ex: 'validation_compte'
 * @param string $description ex: "Compte #3 validé"
 * @param array  $meta        informations supplémentaires (optionnel)
 */
public static function log(string $action, string $description, array $meta = []): void
{
    AppLog::create([
        'user_id'     => auth()->id(),   // l'utilisateur connecté automatiquement
        'action'      => $action,
        'description' => $description,
        'meta'        => $meta,
        'horodatage'  => now(),
    ]);
}
}
