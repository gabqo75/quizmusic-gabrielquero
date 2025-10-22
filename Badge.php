<?php
/**
 * Classe Badge
 * Gestion du système de badges et gamification
 * 
 * 📚 RESPONSABILITÉS :
 * - Vérifier les conditions de déblocage des badges
 * - Attribuer automatiquement les badges
 * - Récupérer les badges d'un utilisateur
 * - Calculer les statistiques de badges
 */

class Badge {
    // 📚 CONCEPT : Propriétés privées encapsulées
    private $id;
    private $code;
    private $nom;
    private $description;
    private $emoji;
    private $couleur;
    private $rarete;
    private $pointsBonus;
    private $ordreAffichage;
    private $obtenuLe = null;// null si pas encore obtenu

    /**
     * Constructeur
     */
    public function __construct(
        int $id,
        string $code,
        string $nom,
        string $description,
        string $emoji,
        string $couleur,
        int $rarete,
        int $pointsBonus,
        int $ordreAffichage,
        ?string $obtenuLe = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->nom = $nom;
        $this->description = $description;
        $this->emoji = $emoji;
        $this->couleur = $couleur;
        $this->rarete = $rarete;
        $this->pointsBonus = $pointsBonus;
        $this->ordreAffichage = $ordreAffichage;
        
        // 📚 Conversion de la date string en objet DateTime
        if ($obtenuLe) {
            $this->obtenuLe = new DateTime($obtenuLe);
        }
    }

    // ====== GETTERS ======

    public function getId(): int {
        return $this->id;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getNom(): string {
        return $this->nom;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getEmoji(): string {
        return $this->emoji;
    }

    public function getCouleur(): string {
        return $this->couleur;
    }

    public function getRarete(): int {
        return $this->rarete;
    }

    public function getPointsBonus(): int {
        return $this->pointsBonus;
    }

    public function estObtenu(): bool {
        return $this->obtenuLe !== null;
    }

    public function getObtenuLe(): ?DateTime {
        return $this->obtenuLe;
    }

    /**
     * Retourne le texte de rareté selon le niveau
     * 
     * @return string Texte formaté avec étoiles
     */
    public function getRareteTexte(): string {
        $etoiles = str_repeat('⭐', $this->rarete);
        $niveaux = [
            1 => 'Commun',
            2 => 'Peu commun',
            3 => 'Rare',
            4 => 'Épique',
            5 => 'Légendaire'
        ];
        return $etoiles . ' ' . ($niveaux[$this->rarete] ?? 'Inconnu');
    }

    // ====== MÉTHODES STATIQUES (logique métier) ======

    /**
     * Récupère tous les badges disponibles
     * 
     * 📚 CONCEPT : Factory Pattern
     * Crée des objets Badge à partir des données SQL
     * 
     * @return array Tableau d'objets Badge
     */
    public static function getTousBadges(): array {
        $pdo = Database::getConnexion();
        $stmt = $pdo->query("
            SELECT * FROM badges
            ORDER BY ordre_affichage ASC
        ");
        
        $badges = [];
        while ($ligne = $stmt->fetch()) {
            $badges[] = new Badge(
                $ligne['id'],
                $ligne['code'],
                $ligne['nom'],
                $ligne['description'],
                $ligne['emoji'],
                $ligne['couleur'],
                $ligne['rarete'],
                $ligne['points_bonus'],
                $ligne['ordre_affichage']
            );
        }
        
        return $badges;
    }

    /**
     * Récupère les badges d'un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @return array Tableau d'objets Badge (avec date d'obtention)
     */
    public static function getBadgesUtilisateur(int $userId): array {
        $pdo = Database::getConnexion();
        
        // 📚 CONCEPT : Jointure pour récupérer badge + date d'obtention
        $stmt = $pdo->prepare("
            SELECT b.*, ub.obtenu_le
            FROM badges b
            INNER JOIN user_badges ub ON b.id = ub.badge_id
            WHERE ub.user_id = ?
            ORDER BY ub.obtenu_le DESC
        ");
        $stmt->execute([$userId]);
        
        $badges = [];
        while ($ligne = $stmt->fetch()) {
            $badges[] = new Badge(
                $ligne['id'],
                $ligne['code'],
                $ligne['nom'],
                $ligne['description'],
                $ligne['emoji'],
                $ligne['couleur'],
                $ligne['rarete'],
                $ligne['points_bonus'],
                $ligne['ordre_affichage'],
                $ligne['obtenu_le']  // Date d'obtention
            );
        }
        
        return $badges;
    }

    /**
     * Récupère tous les badges avec leur statut (obtenu ou non)
     * 
     * @param int $userId ID de l'utilisateur
     * @return array Tableau d'objets Badge
     */
    public static function getTousBadgesAvecStatut(int $userId): array {
        $pdo = Database::getConnexion();
        
        // 📚 CONCEPT : LEFT JOIN pour avoir TOUS les badges
        // Même ceux non obtenus (ub.obtenu_le sera NULL)
        $stmt = $pdo->prepare("
            SELECT b.*, ub.obtenu_le
            FROM badges b
            LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
            ORDER BY b.ordre_affichage ASC
        ");
        $stmt->execute([$userId]);
        
        $badges = [];
        while ($ligne = $stmt->fetch()) {
            $badges[] = new Badge(
                $ligne['id'],
                $ligne['code'],
                $ligne['nom'],
                $ligne['description'],
                $ligne['emoji'],
                $ligne['couleur'],
                $ligne['rarete'],
                $ligne['points_bonus'],
                $ligne['ordre_affichage'],
                $ligne['obtenu_le']  // null si pas obtenu
            );
        }
        
        return $badges;
    }

    /**
     * Vérifie si un utilisateur possède un badge
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $codeBadge Code du badge à vérifier
     * @return bool true si possédé, false sinon
     */
    public static function possedeBadge(int $userId, string $codeBadge): bool {
        $pdo = Database::getConnexion();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as possede
            FROM user_badges ub
            INNER JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ? AND b.code = ?
        ");
        $stmt->execute([$userId, $codeBadge]);
        
        $resultat = $stmt->fetch();
        return $resultat['possede'] > 0;
    }

    /**
     * Attribue un badge à un utilisateur
     * 
     * 📚 CONCEPT : Idempotence
     * Si le badge est déjà possédé, on ne fait rien (pas d'erreur)
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $codeBadge Code du badge à attribuer
     * @param int|null $scoreId ID du score qui a déclenché le badge (optionnel)
     * @return bool true si attribué, false si déjà possédé
     */
    public static function attribuerBadge(int $userId, string $codeBadge, ?int $scoreId = null): bool {
        // 📚 Vérifier si déjà possédé (évite les doublons)
        if (self::possedeBadge($userId, $codeBadge)) {
            return false;  // Déjà possédé
        }
        
        $pdo = Database::getConnexion();
        
        // 📚 Récupérer l'ID du badge à partir de son code
        $stmt = $pdo->prepare("SELECT id FROM badges WHERE code = ?");
        $stmt->execute([$codeBadge]);
        $badge = $stmt->fetch();
        
        if (!$badge) {
            return false;  // Badge inexistant
        }
        
        // 📚 Insérer dans user_badges
        $stmt = $pdo->prepare("
            INSERT INTO user_badges (user_id, badge_id, score_id)
            VALUES (?, ?, ?)
        ");
        
        try {
            $stmt->execute([$userId, $badge['id'], $scoreId]);
            return true;  // Badge attribué avec succès
        } catch (PDOException $e) {
            // 📚 Gestion des erreurs (doublon ou autre)
            return false;
        }
    }

    /**
     * Vérifie et attribue automatiquement tous les badges débloqués
     * 
     * 📚 CONCEPT : Logique métier centralisée
     * Cette méthode est appelée après chaque partie pour vérifier
     * si l'utilisateur a débloqué de nouveaux badges
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $scoreId ID du score venant d'être enregistré
     * @return array Tableau des codes des badges nouvellement obtenus
     */
    public static function verifierEtAttribuerBadges(int $userId, int $scoreId): array {
        $nouveauxBadges = [];
        $pdo = Database::getConnexion();
        
        // ============================================
        // BADGE 1 : Premier Pas
        // Condition : Avoir joué au moins 1 partie
        // ============================================
        if (!self::possedeBadge($userId, 'premier_pas')) {
            // Déjà vérifié implicitement (on est après une partie)
            if (self::attribuerBadge($userId, 'premier_pas', $scoreId)) {
                $nouveauxBadges[] = 'premier_pas';
            }
        }
        
        // ============================================
        // BADGE 2 : Explorateur
        // Condition : Avoir joué tous les thèmes différents
        // ============================================
        if (!self::possedeBadge($userId, 'explorateur')) {
            // Compter le nombre de thèmes différents joués
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT questionnaire_id) as nb_themes
                FROM scores
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $resultat = $stmt->fetch();
            
            // Compter le nombre total de thèmes actifs
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM questionnaires WHERE actif = 1");
            $totalThemes = $stmt->fetch()['total'];
            
            // Si tous les thèmes ont été joués
            if ($resultat['nb_themes'] >= $totalThemes) {
                if (self::attribuerBadge($userId, 'explorateur', $scoreId)) {
                    $nouveauxBadges[] = 'explorateur';
                }
            }
        }
        
        // ============================================
        // BADGE 3 : Perfectionniste
        // Condition : Obtenir 5/5 (score parfait)
        // ============================================
        if (!self::possedeBadge($userId, 'perfectionniste')) {
            // Vérifier si le score actuel est parfait
            $stmt = $pdo->prepare("
                SELECT score, total_questions
                FROM scores
                WHERE id = ?
            ");
            $stmt->execute([$scoreId]);
            $score = $stmt->fetch();
            
            if ($score && $score['score'] == $score['total_questions']) {
                if (self::attribuerBadge($userId, 'perfectionniste', $scoreId)) {
                    $nouveauxBadges[] = 'perfectionniste';
                }
            }
        }
        
        // ============================================
        // BADGE 4 : Marathon
        // Condition : 10 parties en une journée
        // ============================================
        if (!self::possedeBadge($userId, 'marathon')) {
            // Compter les parties jouées aujourd'hui
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as nb_parties
                FROM scores
                WHERE user_id = ?
                AND DATE(date_jeu) = CURDATE()
            ");
            $stmt->execute([$userId]);
            $resultat = $stmt->fetch();
            
            if ($resultat['nb_parties'] >= 10) {
                if (self::attribuerBadge($userId, 'marathon', $scoreId)) {
                    $nouveauxBadges[] = 'marathon';
                }
            }
        }
        
        // ============================================
        // BADGE 5 : Érudit (100 bonnes réponses au total)
        // ============================================
        if (!self::possedeBadge($userId, 'erudit')) {
            $stmt = $pdo->prepare("
                SELECT SUM(score) as total_bonnes
                FROM scores
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $resultat = $stmt->fetch();
            
            if ($resultat['total_bonnes'] >= 100) {
                if (self::attribuerBadge($userId, 'erudit', $scoreId)) {
                    $nouveauxBadges[] = 'erudit';
                }
            }
        }
        
        // ============================================
        // BADGE 6 : Éclair (quiz en moins de 60 secondes)
        // ============================================
        if (!self::possedeBadge($userId, 'eclair')) {
            $stmt = $pdo->prepare("
                SELECT temps_seconde
                FROM scores
                WHERE id = ?
            ");
            $stmt->execute([$scoreId]);
            $score = $stmt->fetch();
            
            if ($score && $score['temps_seconde'] !== null && $score['temps_seconde'] < 60) {
                if (self::attribuerBadge($userId, 'eclair', $scoreId)) {
                    $nouveauxBadges[] = 'eclair';
                }
            }
        }
        
        // ============================================
        // BADGE 7 : Noctambule (jouer entre minuit et 6h)
        // ============================================
        if (!self::possedeBadge($userId, 'noctambule')) {
            $stmt = $pdo->prepare("
                SELECT HOUR(date_jeu) as heure
                FROM scores
                WHERE id = ?
            ");
            $stmt->execute([$scoreId]);
            $score = $stmt->fetch();
            
            if ($score && ($score['heure'] >= 0 && $score['heure'] < 6)) {
                if (self::attribuerBadge($userId, 'noctambule', $scoreId)) {
                    $nouveauxBadges[] = 'noctambule';
                }
            }
        }
        
        return $nouveauxBadges;
    }

    /**
     * Calcule le total des points bonus des badges obtenus
     * 
     * @param int $userId ID de l'utilisateur
     * @return int Total des points bonus
     */
    public static function calculerPointsBonus(int $userId): int {
        $pdo = Database::getConnexion();
        
        $stmt = $pdo->prepare("
            SELECT SUM(b.points_bonus) as total_points
            FROM badges b
            INNER JOIN user_badges ub ON b.id = ub.badge_id
            WHERE ub.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        $resultat = $stmt->fetch();
        return (int)($resultat['total_points'] ?? 0);
    }

    /**
     * Génère le HTML d'un badge (pour affichage)
     * 
     * @param bool $afficherDate Afficher la date d'obtention
     * @return string Code HTML
     */
    public function afficherHTML(bool $afficherDate = false): string {
        $html = '';
        
        // Badge grisé si non obtenu
        $classes = $this->estObtenu() 
            ? "bg-gradient-to-r {$this->couleur}" 
            : "bg-gray-300 opacity-50";
        
        $html .= '<div class="' . $classes . ' rounded-2xl p-6 text-white text-center transform transition-all duration-200 hover:scale-105">';
        
        // Emoji
        $html .= '<div class="text-5xl mb-3">' . $this->emoji . '</div>';
        
        // Nom
        $html .= '<h3 class="font-bold text-xl mb-2">' . htmlspecialchars($this->nom) . '</h3>';
        
        // Description
        $html .= '<p class="text-sm opacity-90 mb-2">' . htmlspecialchars($this->description) . '</p>';
        
        // Rareté
        $html .= '<div class="text-xs mb-2">' . $this->getRareteTexte() . '</div>';
        
        // Points bonus
        if ($this->pointsBonus > 0) {
            $html .= '<div class="bg-white/20 rounded-full px-3 py-1 text-xs inline-block">+' . $this->pointsBonus . ' points</div>';
        }
        
        // Date d'obtention
        if ($afficherDate && $this->estObtenu()) {
            $dateFormatee = $this->obtenuLe->format('d/m/Y');
            $html .= '<div class="text-xs mt-2 opacity-75">Obtenu le ' . $dateFormatee . '</div>';
        }
        
        // Badge verrouillé
        if (!$this->estObtenu()) {
            $html .= '<div class="mt-2 text-xs">🔒 Verrouillé</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
?>