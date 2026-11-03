<?php
// Forward request to project/view-related-reports.php
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: project/view-related-reports.php' . $queryString);
exit;
