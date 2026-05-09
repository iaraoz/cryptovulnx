<?php
// CryptoVulnX - /api/v1/.git-rev
// VULN (API8): Endpoint que filtra revision actual del deploy

require_once __DIR__ . '/../../config/helpers.php';

setCORSHeaders();

jsonResponse([
    'success' => true,
    'rev' => 'a3f5b7c9d1e2f4a6b8c0d2e4f6a8b0c2d4e6f8a0',
    'branch' => 'main',
    'remote' => 'https://gitlab.cryptovulnx.internal/backend/cryptovulnx-api.git',
    'last_commit' => [
        'sha' => 'a3f5b7c9d1e2f4a6b8c0d2e4f6a8b0c2d4e6f8a0',
        'author' => 'Carlos Dev <carlos@cryptovulnx.internal>',
        'date' => '2026-04-15T08:30:00Z',
        'message' => 'WIP - migrating internal endpoints, dont touch /api/internal/rates yet'
    ],
    'deployed_at' => '2026-04-15T09:00:00Z',
    'deployed_by' => 'jenkins-bot',
    'flag' => 'FLAG-ENDPOINT-07: git_rev_filtra_commit_y_remote'
]);
