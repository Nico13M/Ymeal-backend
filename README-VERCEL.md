# Documentation pour déployer Symfony sur Vercel

1. Un fichier vercel.json a été ajouté pour router toutes les requêtes vers public/index.php.
2. Vercel utilisera le builder officiel PHP (@vercel/php).
3. Assurez-vous que vos variables d'environnement (APP_ENV, APP_DEBUG, etc.) sont bien configurées dans le dashboard Vercel.
4. Les fichiers de cache et logs doivent être ignorés ou stockés ailleurs (Vercel est stateless).
5. Pour les assets, privilégiez le dossier public/.

Déploiement :
- Poussez sur GitHub, Vercel détectera le projet et déploiera automatiquement.
- Si besoin, installez le builder PHP sur Vercel : https://vercel.com/docs/frameworks/php

Pour toute erreur, consultez les logs Vercel et adaptez la configuration si besoin.