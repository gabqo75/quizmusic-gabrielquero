<?php
session_start();
require_once 'classes/Database.php';

// Vérification de connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = Database::getConnexion();
$user_id = $_SESSION['user_id'];

// --- 1️⃣ Récupérer les infos utilisateur
$stmt = $pdo->prepare("SELECT pseudo, email, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 2️⃣ Compter le nombre total de parties jouées
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_parties FROM scores WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalParties = $stmt->fetchColumn();

// --- 3️⃣ Trouver le thème préféré (le plus joué)
$stmt = $pdo->prepare("
    SELECT q.titre, COUNT(s.id) AS nb
    FROM scores s
    JOIN questionnaires q ON q.id = s.questionnaire_id
    WHERE s.user_id = ?
    GROUP BY s.questionnaire_id
    ORDER BY nb DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$themePrefere = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 4️⃣ Traitement de la modification du pseudo
$messageSucces = '';
$messageErreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nouveauPseudo = trim($_POST['pseudo']);

    if (strlen($nouveauPseudo) < 3) {
        $messageErreur = "Le pseudo doit contenir au moins 3 caractères.";
    } else {
        // Vérifie si le pseudo est déjà pris
        $stmt = $pdo->prepare("SELECT id FROM users WHERE pseudo = ? AND id != ?");
        $stmt->execute([$nouveauPseudo, $user_id]);

        if ($stmt->fetch()) {
            $messageErreur = "Ce pseudo est déjà utilisé.";
        } else {
            // Met à jour le pseudo
            $stmt = $pdo->prepare("UPDATE users SET pseudo = ? WHERE id = ?");
            $stmt->execute([$nouveauPseudo, $user_id]);

            $_SESSION['user_pseudo'] = $nouveauPseudo;
            $messageSucces = "✅ Votre pseudo a été mis à jour avec succès !";

            // Actualise la variable $user pour l’affichage
            $user['pseudo'] = $nouveauPseudo;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - QuizMusic 🎵</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen text-white">
    <div class="container mx-auto px-6 py-10 max-w-3xl">

        <h1 class="text-4xl font-bold mb-8 text-center">👤 Mon profil</h1>

        <!-- Messages -->
        <?php if ($messageSucces): ?>
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-6">
                <?php echo $messageSucces; ?>
            </div>
        <?php endif; ?>

        <?php if ($messageErreur): ?>
            <div class="bg-red-100 text-red-800 px-4 py-3 rounded mb-6">
                <?php echo $messageErreur; ?>
            </div>
        <?php endif; ?>

        <!-- Informations utilisateur -->
        <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl mb-8">
            <p><strong>Pseudo :</strong> <?php echo htmlspecialchars($user['pseudo']); ?></p>
            <p><strong>Email :</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Date d’inscription :</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
            <p><strong>Nombre total de parties :</strong> <?php echo $totalParties; ?></p>
            <p><strong>Thème préféré :</strong>
                <?php echo $themePrefere ? htmlspecialchars($themePrefere['titre']) : 'Aucun pour le moment 😅'; ?>
            </p>
        </div>

        <!-- Formulaire de modification du pseudo -->
        <form method="POST" class="bg-white/10 backdrop-blur-md p-6 rounded-2xl">
            <h2 class="text-2xl font-semibold mb-4">✏️ Modifier mon pseudo</h2>

            <input
                type="text"
                name="pseudo"
                value="<?php echo htmlspecialchars($user['pseudo']); ?>"
                class="w-full px-4 py-2 rounded-lg text-gray-900 mb-4"
                required
            >

            <button
                type="submit"
                class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                💾 Enregistrer
            </button>
        </form>

        <!-- Lien retour -->
        <div class="text-center mt-8">
            <a href="index.php" class="text-purple-300 hover:text-purple-100">⬅️ Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>
