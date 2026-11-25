<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppLog;
use App\Models\Bien;
use App\Models\Configuration;
use App\Models\SessionEnchere;
use App\Models\Transaction;
use App\Services\AdjudicationService;
use App\Services\WalletService;
use App\Services\NotificationInAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionEnchereController extends Controller
{
    public function __construct(
        private AdjudicationService $adjudicationService,
        private NotificationInAppService $notifications
    ) {}

    /**
     * Clôture les sessions dont la date de fin est dépassée avant tout affichage,
     * pour que l'historique et la page live reflètent toujours la réalité.
     */
    private function cloturerSessionsExpirees(): void
    {
        $this->adjudicationService->cloturerExpirees();
    }

    /**
     * GET /api/ventes-passees
     * Public – Historique paginé des enchères terminées.
     */
    public function historique(Request $request)
    {
        $this->cloturerSessionsExpirees();

        $query = SessionEnchere::where('statut', SessionEnchere::STATUT_TERMINEE)
            ->with([
                'bien:id,titre,categorie_id',
                'bien.categorie:id,libelle',
                'offreGagnante:id,session_id,montant,id_anonyme',
            ])
            ->orderByDesc('date_fin');

        // Filtre par catégorie
        if ($request->filled('categorie_id')) {
            $categorieId = $request->categorie_id;
            $query->whereHas('bien', function ($q) use ($categorieId) {
                $q->where('categorie_id', $categorieId);
            });
        }

        // Filtre par date de début
        if ($request->filled('date_debut')) {
            $query->whereDate('date_fin', '>=', $request->date_debut);
        }

        // Filtre par date de fin
        if ($request->filled('date_fin')) {
            $query->whereDate('date_fin', '<=', $request->date_fin);
        }

        $sessions = $query->paginate(10);

        return response()->json($sessions);
    }

    /**
     * GET /api/sessions/{session}
     * Public – Détail d'une session avec timer et historique des offres.
     * Le prix de réserve est masqué automatiquement par le modèle.
     */
    public function show(SessionEnchere $session)
    {
        $this->cloturerSessionsExpirees();

        $session->load([
            'bien.categorie',
            'bien.photos',
            'offres' => function ($q) {
                $q->orderByDesc('montant')->limit(20);
            },
        ]);

        // Formater les offres pour masquer l'identité des enchérisseurs
        $offresFormatees = [];
        foreach ($session->offres as $offre) {
            $offresFormatees[] = [
                'montant'    => $offre->montant,
                'id_anonyme' => $offre->id_anonyme,
                'heure'      => $offre->created_at->format('H:i:s'),
            ];
        }

        return response()->json([
            'data' => [
                'id'                     => $session->id,
                'bien'                   => $session->bien,
                'statut'                 => $session->statut,
                'date_debut'             => $session->date_debut,
                'date_fin'               => $session->date_fin,
                'mise_a_prix_effective'  => $session->mise_a_prix_effective,
                'montant_min_surenchere' => $session->montant_min_surenchere,
                // prix_reserve intentionnellement absent — confidentiel
                'meilleure_offre'        => $session->meilleure_offre?->montant ?? 0,
                'id_meilleur'            => $session->meilleure_offre?->id_anonyme ?? null,
                'secondes_restantes'     => $session->secondes_restantes,
                'offres'                 => $offresFormatees,
            ],
        ]);
    }

    /**
     * Libellé français d'un statut de session (réutilisé dans la vue du compte rendu).
     */
    private function libelleStatut(string $statut): string
    {
        return match ($statut) {
            SessionEnchere::STATUT_A_VENIR     => 'À venir',
            SessionEnchere::STATUT_EN_COURS    => 'En cours',
            SessionEnchere::STATUT_TERMINEE    => 'Terminée',
            SessionEnchere::STATUT_INTERROMPUE => 'Interrompue',
            default                            => $statut,
        };
    }

    /**
     * GET /api/sessions/{session}/compte-rendu
     * auth:sanctum — commissaire propriétaire, superviseur affecté ou admin.
     * Génère un compte rendu PDF téléchargeable de la session (terminée/interrompue).
     */
    public function compteRendu(Request $request, SessionEnchere $session)
    {
        // Seuls le commissaire propriétaire, le superviseur affecté ou un admin
        // peuvent télécharger ce document (il contient le prix de réserve).
        $user = $request->user();
        $proprietaire = ($session->commissaire_id === $user->id)
            || ($session->superviseur_id === $user->id)
            || $user->isAdmin();

        if (!$proprietaire) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        // Un compte rendu n'a de sens qu'à l'issue d'une session.
        if (!in_array($session->statut, [SessionEnchere::STATUT_TERMINEE, SessionEnchere::STATUT_INTERROMPUE])) {
            return response()->json(['message' => 'La session n\'est pas encore terminée.'], 422);
        }

        // Charger toutes les données nécessaires au document.
        $session->load([
            'bien.categorie',
            'commissaire',
            'superviseur',
            'offres' => function ($q) {
                $q->orderByDesc('montant');
            },
        ]);

        // L'offre gagnante est celle marquée 'est_gagnante' à la clôture.
        $offreGagnante = $session->offres->firstWhere('est_gagnante', true)
            ?? $session->offreGagnante;

        // Participants : liste des enchérisseurs ayant placé au moins une offre.
        $participants = $session->offres->pluck('encherisseur_id')->unique();

        // Variable de la vue : bien, commissaire, superviseur, offres
        $bien        = $session->bien;
        $commissaire = $session->commissaire;
        $superviseur = $session->superviseur;
        $offres      = $session->offres;

        $statutLibelle = $this->libelleStatut($session->statut);

        // Générer le PDF à partir de la vue Blade dédiée.
        $pdf = Pdf::loadView('CompteRendu.pdf', compact(
            'session',
            'offreGagnante',
            'participants',
            'statutLibelle',
            'bien',
            'commissaire',
            'superviseur',
            'offres'
        ))->setPaper('a4');

        return $pdf->download('compte_rendu_session_' . $session->id . '_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * GET /api/commissaire/sessions
     * auth:sanctum + role:commissaire_priseur
     * Liste des sessions créées par le commissaire connecté.
     */
    public function indexCommissaire(Request $request)
    {
        $sessions = SessionEnchere::where('commissaire_id', $request->user()->id)
            ->with('bien:id,titre')
            ->orderByDesc('date_debut')
            ->paginate(10);

        return response()->json($sessions);
    }

    /**
     * POST /api/sessions
     * auth:sanctum + role:commissaire_priseur
     * Créer une nouvelle session d'enchère.
     */
    public function store(Request $request)
    {
        $request->validate([
            'bien_id'                => 'required|exists:biens,id',
            'superviseur_id'         => 'nullable|exists:users,id',
            'date_debut'             => 'required|date|after:now',
            'date_fin'               => 'required|date|after:date_debut',
            'mise_a_prix_effective'  => 'required|numeric|min:0',
            'montant_min_surenchere' => 'required|numeric|min:1',
            'prix_reserve'           => 'nullable|numeric|min:0',
        ]);

        // Vérifier que le bien est publié et appartient bien à ce commissaire
        $bien = Bien::where('id', $request->bien_id)
            ->where('cree_par', $request->user()->id)
            ->where('statut', Bien::STATUT_PUBLIE)
            ->first();

        if (!$bien) {
            return response()->json(['message' => 'Bien invalide ou déjà en vente.'], 422);
        }

        $session = SessionEnchere::create([
            'bien_id'                => $request->bien_id,
            'superviseur_id'         => $request->superviseur_id,
            'commissaire_id'         => $request->user()->id,
            'date_debut'             => $request->date_debut,
            'date_fin'               => $request->date_fin,
            'mise_a_prix_effective'  => $request->mise_a_prix_effective,
            'montant_min_surenchere' => $request->montant_min_surenchere,
            'prix_reserve'           => $request->prix_reserve,
            'statut'                 => SessionEnchere::STATUT_A_VENIR,
        ]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'creation_session',
            'description' => "Session #{$session->id} créée pour le bien #{$request->bien_id}",
            'horodatage'  => now(),
        ]);

        // Notifier le superviseur affecté (s'il y en a un)
        if ($session->superviseur_id) {
            $this->notifications->envoyer(
                $session->superviseur,
                'Nouvelle session affectée',
                "Vous êtes affecté à la session #{$session->id} ({$session->bien->titre}).",
                '/superviseur',
                'sensors',
                'session'
            );
        }

        return response()->json([
            'message' => 'Session créée.',
            'data'    => $session->load('bien'),
        ], 201);
    }

    /**
     * PUT /api/sessions/{session}
     * auth:sanctum + role:commissaire_priseur
     * Modifier une session (seulement si elle n'a pas encore démarré).
     */
   public function update(Request $request, SessionEnchere $session)
{
    if ($session->commissaire_id !== $request->user()->id) {
        return response()->json(['message' => 'Action non autorisée.'], 403);
    }

    if ($session->statut !== SessionEnchere::STATUT_A_VENIR) {
        return response()->json(['message' => 'La session ne peut plus être modifiée.'], 422);
    }

    // Calculer la date_debut de référence pour valider date_fin
    $dateDebutRef = $request->date_debut ?? $session->date_debut;

    $request->validate([
        'superviseur_id'         => 'nullable|exists:users,id',
        'date_debut'             => 'sometimes|date|after:now',
        'date_fin'               => 'sometimes|date|after:' . $dateDebutRef,
        'mise_a_prix_effective'  => 'sometimes|numeric|min:0',
        'montant_min_surenchere' => 'sometimes|numeric|min:1',
        'prix_reserve'           => 'nullable|numeric|min:0',
    ]);

    $session->update([
        'superviseur_id'         => $request->superviseur_id ?? $session->superviseur_id,
        'date_debut'             => $request->date_debut ?? $session->date_debut,
        'date_fin'               => $request->date_fin ?? $session->date_fin,
        'mise_a_prix_effective'  => $request->mise_a_prix_effective ?? $session->mise_a_prix_effective,
        'montant_min_surenchere' => $request->montant_min_surenchere ?? $session->montant_min_surenchere,
        'prix_reserve'           => $request->prix_reserve ?? $session->prix_reserve,
    ]);

    AppLog::create([
        'user_id'     => $request->user()->id,
        'action'      => 'modification_session',
        'description' => "Session #{$session->id} modifiée",
        'horodatage'  => now(),
    ]);

    return response()->json([
        'message' => 'Session modifiée.',
        'data'    => $session->fresh(),
    ]);
}

/**
 * Vérification que la session accepte les offres
 * À appeler dans ton OffreController avant d'enregistrer une offre
 */
public function verifierDisponibilite(SessionEnchere $session): bool
{
    return $session->statut === SessionEnchere::STATUT_EN_COURS
        && now()->between($session->date_debut, $session->date_fin);
}

    /**
     * POST /api/sessions/{session}/interrompre
     * auth:sanctum + role:commissaire_priseur
     * Suspendre temporairement une enchère en cours.
     */
    public function interrompre(Request $request, SessionEnchere $session)
    {
        if ($session->commissaire_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($session->statut !== SessionEnchere::STATUT_EN_COURS) {
            return response()->json(['message' => 'La session n\'est pas en cours.'], 422);
        }

        $session->update(['statut' => SessionEnchere::STATUT_INTERROMPUE]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'interruption_session',
            'description' => "Session #{$session->id} interrompue",
            'horodatage'  => now(),
        ]);

        // Prévenir le superviseur affecté
        if ($session->superviseur_id) {
            $this->notifications->envoyer(
                $session->superviseur,
                'Session suspendue',
                "La session #{$session->id} a été interrompue.",
                '/superviseur',
                'pause_circle',
                'session'
            );
        }

        return response()->json(['message' => 'Enchère suspendue.']);
    }

    /**
     * POST /api/sessions/{session}/reprendre
     * auth:sanctum + role:commissaire_priseur
     * Reprendre une enchère qui avait été interrompue.
     */
    public function reprendre(Request $request, SessionEnchere $session)
    {
        if ($session->commissaire_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if ($session->statut !== SessionEnchere::STATUT_INTERROMPUE) {
            return response()->json(['message' => 'La session n\'est pas interrompue.'], 422);
        }

        $session->update(['statut' => SessionEnchere::STATUT_EN_COURS]);

        // Enregistrer l'action dans les logs
        AppLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'reprise_session',
            'description' => "Session #{$session->id} reprise",
            'horodatage'  => now(),
        ]);

        // Prévenir le superviseur affecté
        if ($session->superviseur_id) {
            $this->notifications->envoyer(
                $session->superviseur,
                'Session reprise',
                "La session #{$session->id} a été reprise.",
                '/superviseur',
                'play_circle',
                'session'
            );
        }

        return response()->json(['message' => 'Enchère reprise.']);
    }

    /**
     * GET /api/superviseur/sessions
     * auth:sanctum + role:superviseur
     * Liste des sessions affectées au superviseur connecté.
     */
    public function indexSuperviseur(Request $request)
    {
        $sessions = SessionEnchere::where('superviseur_id', $request->user()->id)
            ->with('bien:id,titre')
            ->orderByDesc('date_debut')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * GET /api/superviseur/sessions/{session}
     * auth:sanctum + role:superviseur
     * Vue temps réel pour la supervision (polling).
     */
    public function superviser(Request $request, SessionEnchere $session)
    {
        if ($session->superviseur_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $session->load([
            'bien:id,titre,categorie_id',
            'bien.categorie:id,libelle',
            'offres' => function ($q) {
                $q->orderByDesc('montant')->limit(30);
            },
        ]);

        // Formater l'historique des offres
        $historiqueOffres = [];
        foreach ($session->offres as $offre) {
            $historiqueOffres[] = [
                'montant'    => $offre->montant,
                'id_anonyme' => $offre->id_anonyme,
                'heure'      => $offre->created_at->format('H:i:s'),
            ];
        }

        // Construire la liste des participants (une ligne par enchérisseur)
        $participants    = [];
        $enchereursVus   = [];

        foreach ($session->offres as $offre) {
            $encherisseurId = $offre->encherisseur_id;

            if (!in_array($encherisseurId, $enchereursVus)) {
                $enchereursVus[] = $encherisseurId;

                // Trouver la meilleure offre de cet enchérisseur
                $meilleureOffre = $session->offres
                    ->where('encherisseur_id', $encherisseurId)
                    ->max('montant');

                $participants[] = [
                    'id_anonyme'      => $offre->id_anonyme,
                    'meilleure_offre' => $meilleureOffre,
                ];
            }
        }

        return response()->json([
            'data' => [
                'session' => [
                    'id'       => $session->id,
                    'statut'   => $session->statut,
                    'date_fin' => $session->date_fin,
                ],
                'bien'                => $session->bien,
                'meilleure_offre'     => $session->meilleure_offre?->montant ?? 0,
                'id_meilleur'         => $session->meilleure_offre?->id_anonyme ?? null,
                'secondes_restantes'  => $session->secondes_restantes,
                'historique_offres'   => $historiqueOffres,
                'participants'        => $participants,
            ],
        ]);
    }

    /**
     * POST /api/sessions/{session}/payer
     * auth:sanctum + role:encherisseur (validé)
     *
     * Le gagnant règle le prix adjugé + les frais d'adjudication depuis son wallet.
     * Règle métier (CDC) : total = prix adjugé + 8% de frais − caution déjà versée.
     * Enregistre `paiement_effectue_le` (empêche la relance/la pénalité) et marque le bien "vendu".
     */
    public function payer(Request $request, SessionEnchere $session, WalletService $walletService)
    {
        $encherisseur = $request->user()->encherisseur;

        if (!$encherisseur) {
            return response()->json(['message' => 'Profil enchérisseur introuvable.'], 403);
        }

        // La session doit être terminée et avoir un gagnant
        if ($session->statut !== SessionEnchere::STATUT_TERMINEE || !$session->offre_gagnante_id) {
            return response()->json(['message' => 'Aucun paiement n\'est attendu pour cette session.'], 422);
        }

        $offreGagnante = $session->offreGagnante;

        // Seul le gagnant peut régler cette session
        if (!$offreGagnante || $offreGagnante->encherisseur_id !== $encherisseur->id) {
            return response()->json(['message' => 'Vous n\'êtes pas le gagnant de cette session.'], 403);
        }

        if ($session->paiement_effectue_le) {
            return response()->json(['message' => 'Le paiement a déjà été effectué.'], 422);
        }

        // Montant dû : prix adjugé + frais d'adjudication (%) − caution déjà prélevée
        $prix    = (float) $offreGagnante->montant;
        $taux    = (float) Configuration::valeur('taux_frais_adjudication', 8);
        $frais   = round($prix * $taux / 100, 2);
        $caution = $session->getCautionRequise();
        $total   = max(0, round($prix + $frais - $caution, 2));

        try {
            $resultat = DB::transaction(function () use ($session, $encherisseur, $prix, $frais, $caution, $total, $request, $walletService) {
                // Revérifier sous verrou (idempotence : ne pas débiter deux fois)
                $session = SessionEnchere::whereKey($session->id)->lockForUpdate()->firstOrFail();

                if ($session->paiement_effectue_le) {
                    return ['erreur' => 422, 'message' => 'Le paiement a déjà été effectué.'];
                }

                $transaction = $walletService->debiter(
                    $encherisseur,
                    $total,
                    Transaction::TYPE_PAIEMENT_FINAL,
                    $session->id
                );

                $session->update(['paiement_effectue_le' => now()]);

                // Le bien devient vendu
                $session->bien->update(['statut' => 'vendu']);

                AppLog::create([
                    'user_id'     => $request->user()->id,
                    'action'      => 'paiement_final',
                    'description' => "Paiement final de {$total} FCFA (prix {$prix} + frais {$frais} - caution {$caution}) pour la session #{$session->id}",
                    'horodatage'  => now(),
                ]);

                return ['transaction' => $transaction];
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (isset($resultat['erreur'])) {
            return response()->json(['message' => $resultat['message']], $resultat['erreur']);
        }

        // Prévenir le commissaire propriétaire du paiement final
        $this->notifications->envoyer(
            $session->commissaire,
            'Paiement final reçu',
            "Le gagnant a réglé {$total} FCFA pour la session #{$session->id}.",
            '/commissaire/sessions',
            'payments',
            'paiement'
        );

        return response()->json([
            'message'    => 'Paiement effectué.',
            'prix'       => $prix,
            'frais'      => $frais,
            'caution'    => $caution,
            'total'      => $total,
            'reference'  => $resultat['transaction']->id,
        ]);
    }
}
