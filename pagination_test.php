<!DOCTYPE html>
<html>
<head>
    <title>Pagination Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-result { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
        .success { background-color: #d4edda; }
        .error { background-color: #f8d7da; }
    </style>
</head>
<body>
    <h1>Pagination Implementation Test</h1>
    
    <?php
    // Test pagination logic
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;
    $offset = ($page - 1) * $per_page;
    
    // Simulate total records
    $total_records = 157; // Example: 157 referrals
    $total_pages = ceil($total_records / $per_page);
    
    echo "<div class='test-result success'>";
    echo "<h3>Pagination Variables:</h3>";
    echo "Page: $page<br>";
    echo "Per Page: $per_page<br>";
    echo "Offset: $offset<br>";
    echo "Total Records: $total_records<br>";
    echo "Total Pages: $total_pages<br>";
    echo "</div>";
    
    // Test pagination display logic
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    echo "<div class='test-result'>";
    echo "<h3>Pagination Display:</h3>";
    echo "Start Page: $start_page<br>";
    echo "End Page: $end_page<br>";
    echo "Show First: " . ($start_page > 1 ? 'Yes' : 'No') . "<br>";
    echo "Show Last: " . ($end_page < $total_pages ? 'Yes' : 'No') . "<br>";
    echo "</div>";
    
    // Test records display
    $showing_from = (($page - 1) * $per_page) + 1;
    $showing_to = min($page * $per_page, $total_records);
    
    echo "<div class='test-result success'>";
    echo "<h3>Records Display:</h3>";
    echo "Showing $showing_from to $showing_to of $total_records referrals";
    echo "</div>";
    ?>
    
    <h3>Test Different Page Sizes:</h3>
    <a href="?page=1&per_page=10">10 per page</a> |
    <a href="?page=1&per_page=25">25 per page</a> |
    <a href="?page=1&per_page=50">50 per page</a> |
    <a href="?page=1&per_page=100">100 per page</a>
    
    <h3>Test Different Pages (25 per page):</h3>
    <a href="?page=1&per_page=25">Page 1</a> |
    <a href="?page=2&per_page=25">Page 2</a> |
    <a href="?page=3&per_page=25">Page 3</a> |
    <a href="?page=7&per_page=25">Page 7 (Last)</a>
    
</body>
</html>