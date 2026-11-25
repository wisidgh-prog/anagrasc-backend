<?php
use App\Http\Controllers\Api\SessionEnchereController;
use App\Http\Controllers\Api\BienController;
use App\Http\Controllers\Api\OffreController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\CategorieController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PieceController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ConfigurationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\MailTestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CautionsPaiementsController;



//ROUTE PROTÉGÉES DE CONNEXION ET DECONNECTION PAR AUTH:SANCTUM
 Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Notifications internes (cloche)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/compteur', [NotificationController::class, 'compteur']);
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutMarquerLues']);
    Route::post('/notifications/{notification}/lire', [NotificationController::class, 'marquerLue']);
    // Paramètres non sensibles utiles aux formulaires (ex. surenchère minimum par défaut)
    Route::get('/configurations-publiques', [ConfigurationController::class, 'publiques']);
    // Compte rendu PDF d'une session terminée (commissaire, superviseur affecté ou admin)
    Route::get('/sessions/{session}/compte-rendu', [SessionEnchereController::class, 'compteRendu']);
 });
 /*
    |--------------------------------------------------------------------------
    | PUBLIQUE
    |--------------------------------------------------------------------------
    */
//Routes pour la connexion et l'inscription
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
// Routes pour les biens
Route::get('/biens', [BienController::class, 'index']);
Route::get('/biens/{bien}', [BienController::class, 'show']);
// Routes pour les enchères
Route::get('/ventes-passees', [SessionEnchereController::class, 'historique']);
Route::get('/sessions/{session}', [SessionEnchereController::class, 'show']);
// Route pour les offres d'une session (polling)
Route::get('/sessions/{session}/offres', [OffreController::class, 'index']);

// Route pour les catégories
Route::get('/categories', [CategorieController::class, 'index']);
/*
    |--------------------------------------------------------------------------
    | ENCHERISSEUR
    |--------------------------------------------------------------------------
    */

// Enchérisseur valide
Route::middleware(['auth:sanctum', 'role:encherisseur', 'encherisseur.valide'])->group(function () {
    // Participation aux enchères
    Route::middleware('permission:participer_encheres')->group(function () {
        Route::post('/sessions/{session}/offrir', [OffreController::class, 'store']);
        Route::get('/mes-participations', [OffreController::class, 'mesParticipations']);
        Route::get('/mes-offres-en-cours', [OffreController::class, 'offresEnCours']);
        // Paiement final du gagnant (prix adjugé + frais d'adjudication)
        Route::post('/sessions/{session}/payer', [SessionEnchereController::class, 'payer']);
    });

    // Portefeuille (wallet)
    Route::middleware('permission:gerer_wallet')->group(function () {
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::post('/wallet/deposer', [WalletController::class, 'deposer']);
        Route::get('/mes-cautions', [WalletController::class, 'cautions']);
    });
});



 /*
    |--------------------------------------------------------------------------
    | COMMISSAIRE-PRISEUR
    |--------------------------------------------------------------------------
    */

Route::middleware(['auth:sanctum', 'role:commissaire_priseur'])->group(function () {
    //ROUTES POUR LES BIENS
    Route::middleware('permission:gerer_biens')->group(function () {
        Route::get('/commissaire/biens', [BienController::class, 'mesBiens']);
        Route::post('/biens', [BienController::class, 'store']);
        Route::put('/biens/{bien}', [BienController::class, 'update']);
        Route::delete('/biens/{bien}', [BienController::class, 'destroy']);
        Route::post('/biens/{bien}/photos', [BienController::class, 'ajouterPhotos']);
    });

    //ROUTES POUR LES SESSIONS D'ENCHÈRES
    Route::middleware('permission:gerer_sessions')->group(function () {
        Route::get('/commissaire/sessions', [SessionEnchereController::class, 'indexCommissaire']);
        Route::post('/sessions', [SessionEnchereController::class, 'store']);
        Route::put('/sessions/{session}', [SessionEnchereController::class, 'update']);
        Route::post('/sessions/{session}/interrompre', [SessionEnchereController::class, 'interrompre']);
        Route::post('/sessions/{session}/reprendre', [SessionEnchereController::class, 'reprendre']);
        Route::get('/superviseurs', [AdminController::class, 'superviseurs']);
    });

    // Routes pour les incidents signalés (traitement)
    Route::middleware('permission:traiter_incident')->group(function () {
        Route::get('/incidents', [IncidentController::class, 'index']);
        Route::post('/incidents/{incident}/decision', [IncidentController::class, 'decision']);
    });

    //route pour le dashboard commissaire
    Route::get('/commissaire/dashboard', [DashboardController::class, 'commissaire'])
        ->middleware('permission:superviser_encheres');
});

 /*
    |--------------------------------------------------------------------------
    | SUPERVISEUR
    |--------------------------------------------------------------------------
    */

// Routes (consultation et supervision en temps réel)
Route::middleware(['auth:sanctum', 'role:superviseur'])->group(function () {
    Route::middleware('permission:superviser_encheres')->group(function () {
        Route::get('/superviseur/sessions', [SessionEnchereController::class, 'indexSuperviseur']);
        Route::get('/superviseur/sessions/{session}', [SessionEnchereController::class, 'superviser']);
    });

    // Route pour signaler un incident
    Route::post('/incidents', [IncidentController::class, 'store'])
        ->middleware('permission:signaler_incident');
});

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRATEUR
    |--------------------------------------------------------------------------
    */


 Route::middleware(['auth:sanctum', 'role:administrateur'])->group(function () {
    // Routes pour la gestion des utilisateurs
    Route::middleware('permission:gerer_utilisateurs')->group(function () {
        Route::get('/utilisateurs', [AdminController::class, 'utilisateurs']);
        Route::post('/utilisateurs/{user}/valider', [AdminController::class, 'validerCompte']);
        Route::post('/utilisateurs/{user}/rejeter', [AdminController::class, 'rejeterCompte']);
        Route::post('/utilisateurs/{user}/suspendre', [AdminController::class, 'suspendreCompte']);
        Route::post('/utilisateurs/interne', [AdminController::class, 'creerCompteInterne']);

        //Route pour le controlle des pieces
        Route::get('/pieces/{user}', [PieceController::class, 'show']);
        Route::get('/utilisateurs/{user}/piece/recto', [PieceController::class, 'afficherRecto']);
        Route::get('/utilisateurs/{user}/piece/verso', [PieceController::class, 'afficherVerso']);

        // Routes pour la gestion des rôles et permissions
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
    });

    // Logs de traçabilité
    Route::middleware('permission:consulter_logs')->group(function () {
        Route::get('/logs', [LogController::class, 'index']);
        Route::get('/logs/export', [LogController::class, 'export']);
    });

    // Paramètres système / configuration
    Route::middleware('permission:modifier_config')->group(function () {

    //Route de vérification SMTP (envoi d'UN SEUL email)
        Route::post('/mail-test', [MailTestController::class, 'envoyer']);
        Route::get('/configurations', [ConfigurationController::class, 'index']);
        Route::put('/configurations/{configuration}', [ConfigurationController::class, 'update']);
    });

    // Validation des biens soumis par les commissaires
    Route::middleware('permission:gerer_biens')->group(function () {
        Route::get('admin/biens', [BienController::class, 'indexAdmin']);
        Route::post('/biens/{bien}/valider', [BienController::class, 'valider']);
        Route::post('/biens/{bien}/rejeter', [BienController::class, 'rejeter']);
    });

    // Route pour le dashboard admin
    Route::get('/admin/dashboard', [DashboardController::class, 'admin']);

    // Route pour la page Finance (traçabilité admin)
    Route::get('/admin/finance', [DashboardController::class, 'finance']);

    // Page "Cautions & Paiements" (traçabilité admin)
    Route::get('/admin/sessions', [CautionsPaiementsController::class, 'sessions']);
    Route::get('/admin/cautions-paiements', [CautionsPaiementsController::class, 'index']);
    Route::post('/admin/sessions/{session}/confirmer-paiement', [CautionsPaiementsController::class, 'confirmerPaiement']);
    Route::post('/admin/cautions/liberer', [CautionsPaiementsController::class, 'libererCaution']);
});
