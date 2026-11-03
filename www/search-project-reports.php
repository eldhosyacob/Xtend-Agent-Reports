<?php
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: project/search-project-reports.php' . $queryString);
exit;

