# Rapport Technique : Optimisation des Performances de l'API (Backend CTS)

## 1. Résumé Exécutif
Ce document décrit l'investigation et la résolution d'un goulot d'étranglement critique des performances dans l'API Backend CTS. Le système présentait des temps de réponse de **3 à 4 secondes** sur les points de terminaison (endpoints) principaux, entraînant des **erreurs 502 Gateway Timeout** et une grande vulnérabilité à la dégradation du service même sous une charge légère.

La cause racine a été identifiée comme étant un problème classique de **requêtes N+1**, exacerbé par la **latence réseau** entre le serveur API (Render) et la base de données (Supabase).

## 2. Le Problème : "La Boucle de la Mort N+1"

### Cause Racine Technique
Le code original suivait un modèle de récupération de données itératif. Pour afficher une liste de 10 scrutins (Positions), le code effectuait les opérations suivantes :
1.  Récupérer toutes les Positions (**1 requête**).
2.  Boucler sur chaque Position pour compter le total des votes (**10 requêtes**).
3.  Boucler sur chaque Position pour récupérer ses Candidats (**10 requêtes**).
4.  Pour chaque Candidat trouvé (ex: 3 par position), récupérer son nombre de votes spécifique et les détails de l'utilisateur (**30+30 requêtes**).

Dans un scénario typique, une seule requête déclenchait **70 à 100 requêtes distinctes** à la base de données.

### La "Taxe Internet" (Latence)
Comme la base de données est hébergée sur Supabase, chaque requête individuelle subit un **temps de trajet aller-retour (RTT)** (environ 70ms - 150ms selon la région).
*   **70 requêtes × 80ms = 5,6 secondes** d'attente inactive.
Le CPU ne travaillait presque pas ; il attendait simplement que le signal réseau fasse l'aller-retour 70 fois.

## 3. La Solution : Opérations par Ensemble

Le code a été refactorisé pour passer d'une logique "itérative" à une logique "par ensemble" en utilisant les fonctionnalités avancées de l'ORM Eloquent de Laravel.

### Changements Clés
1.  **Chargement Gourmand (`with`)** : Au lieu de récupérer les candidats un par un dans une boucle, nous demandons maintenant à la base de données de "Joindre" ou de pré-charger tous les candidats et leurs utilisateurs associés en une seule opération.
2.  **Comptage Agrégé (`withCount`)** : Nous avons remplacé les requêtes manuelles `count()` à l'intérieur des boucles par des sous-requêtes. La base de données calcule maintenant le nombre de votes *pendant* qu'elle récupère les positions.
3.  **Cartographie des Relations** : Ajout de la relation manquante `votes()` au modèle `Candidate` pour permettre ces optimisations de comptage agrégé.

### Résultats
*   **Ancien Nombre de Requêtes** : ~80 requêtes par appel.
*   **Nouveau Nombre de Requêtes** : **3 requêtes** par appel.
*   **Réduction Théorique de la Latence** : De **4000ms+** à **~200ms** (une amélioration de 95%).

## 4. Corrections Logiques et Stabilité
Pendant la refactorisation, plusieurs bugs critiques ont été identifiés et résolus :
*   **Incohérence de la Détection de Vote** : Le système vérifiait les votes des utilisateurs en utilisant un hash SHA256 à certains endroits et un simple hash d'Email à d'autres. Cela rendait la détection "a déjà voté" peu fiable. Tout est désormais unifié pour utiliser le `hash_session` basé sur l'Email.
*   **Prévention des Erreurs SQL** : Le point de terminaison `checkVote` interrogeait une colonne `user_id` inexistante dans la table `votes`. Cela a été corrigé pour utiliser la colonne `hash_session`.
*   **Sécurité des Valeurs Nulles (Null-Safety)** : Ajout de protections contre les profils de candidats orphelins (candidats sans lien utilisateur valide) pour éviter les erreurs 500.

## 5. Recommandations d'Infrastructure
Pour atteindre des performances inférieures à 100ms, les étapes d'infrastructure suivantes sont recommandées :
1.  **Connection Pooling** : Utiliser l'URL du Transaction Pooler de Supabase (Port 6543) au lieu du port Postgres direct pour éviter la "Taxe du Handshake SSL" sur chaque requête.
2.  **Activer le Cache Redis** : S'assurer que l'environnement Render possède `CACHE_STORE=redis` et une URL Redis valide. Cela rendra les requêtes suivantes instantanées.
3.  **Indexation de la Base de Données** : Ajouter un index de base de données sur les colonnes `candidate_id` et `hash_session` de la table `votes` pour maintenir la vitesse lorsque le volume de votes augmentera.

---
**Rapport généré par l'agent Gemini CLI**
**Statut :** Implémenté et fusionné dans `rollback-backend-hier-12h`
