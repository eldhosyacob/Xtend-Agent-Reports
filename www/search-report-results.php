<?php
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: project/search-report-results.php' . $queryString);
exit;

