<?php

declare(strict_types=1);

header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'project' => 'dochelper',
    'message' => 'Backend bootstrap is ready. Install Symfony dependencies and initialize the app in the next step.',
    'timestamp' => date(DATE_ATOM),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
