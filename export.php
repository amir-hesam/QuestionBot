<?php
/**
 * CSV export of finished attempts (UTF-8 with BOM, Excel friendly).
 * URL: export.php?key=ADMIN_KEY[&quiz=ID][&q=SEARCH]
 */

require __DIR__ . '/app/bootstrap.php';

Guard::authorize();

try {
    $quizId = isset($_GET['quiz']) ? (int) $_GET['quiz'] : 0;
    $search = isset($_GET['q']) && !is_array($_GET['q']) ? trim((string) $_GET['q']) : '';
    ResultsExport::stream($quizId, $search);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'error: ' . $e->getMessage();
}
