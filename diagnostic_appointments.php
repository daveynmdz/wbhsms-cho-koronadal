<?php
// Comprehensive diagnostic script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Appointments Diagnostic</title></head><body>\n";
echo "<h1>Appointments Page Diagnostic</h1>\n";

// Test 1: PHP Syntax Check
echo "<h2>Test 1: PHP Syntax</h2>\n";
$syntax_check = shell_exec('php -l "c:/xampp/htdocs/wbhsms-cho-koronadal/pages/patient/appointment/appointments.php" 2>&1');
echo "<pre>PHP Syntax Check Result:\n" . htmlspecialchars($syntax_check ?? 'Could not run syntax check') . "</pre>\n";

// Test 2: File Permissions
echo "<h2>Test 2: File Access</h2>\n";
$file_path = "c:/xampp/htdocs/wbhsms-cho-koronadal/pages/patient/appointment/appointments.php";
if (file_exists($file_path)) {
    echo "✅ File exists<br>\n";
    echo "📁 File size: " . filesize($file_path) . " bytes<br>\n";
    echo "🕐 Last modified: " . date("Y-m-d H:i:s", filemtime($file_path)) . "<br>\n";
    if (is_readable($file_path)) {
        echo "✅ File is readable<br>\n";
    } else {
        echo "❌ File is not readable<br>\n";
    }
} else {
    echo "❌ File does not exist<br>\n";
}

// Test 3: Check for JavaScript Function
echo "<h2>Test 3: JavaScript Function Check</h2>\n";
echo "<p>Checking if showCancelModal function exists in the file...</p>\n";
if (file_exists($file_path)) {
    $file_content = file_get_contents($file_path);
    if (strpos($file_content, 'function showCancelModal') !== false) {
        echo "✅ showCancelModal function found in file<br>\n";
        
        // Count occurrences
        $count = substr_count($file_content, 'function showCancelModal');
        echo "📊 Function defined " . $count . " time(s)<br>\n";
        
        // Find line numbers
        $lines = explode("\n", $file_content);
        $line_numbers = [];
        foreach ($lines as $line_num => $line) {
            if (strpos($line, 'function showCancelModal') !== false) {
                $line_numbers[] = $line_num + 1;
            }
        }
        echo "📍 Found on lines: " . implode(', ', $line_numbers) . "<br>\n";
    } else {
        echo "❌ showCancelModal function NOT found in file<br>\n";
    }
    
    // Check for onclick handlers
    $onclick_count = substr_count($file_content, 'onclick="showCancelModal');
    echo "🖱️ onclick handlers calling showCancelModal: " . $onclick_count . "<br>\n";
}

// Test 4: Generate sample onclick
echo "<h2>Test 4: Sample onClick Generation</h2>\n";
$sample_id = 123;
$sample_number = 'APT-' . str_pad($sample_id, 8, '0', STR_PAD_LEFT);
$safe_sample_number = htmlspecialchars($sample_number, ENT_QUOTES, 'UTF-8');

echo "<p>Sample onclick attribute generation:</p>\n";
echo "<code>onclick=\"showCancelModal($sample_id, '$safe_sample_number')\"</code><br>\n";

echo "<h2>Test 5: Live JavaScript Test</h2>\n";
echo '<button onclick="testShowCancel()">Test showCancelModal Function</button><br>';
echo '<div id="js-test-result" style="margin-top: 10px; padding: 10px; background: #f0f0f0;"></div>';

echo '<script>
function showCancelModal(id, number) {
    document.getElementById("js-test-result").innerHTML = "✅ showCancelModal worked! ID: " + id + ", Number: " + number;
    alert("Function test successful!\\nID: " + id + "\\nNumber: " + number);
}

function testShowCancel() {
    try {
        showCancelModal(' . $sample_id . ', "' . $safe_sample_number . '");
    } catch (e) {
        document.getElementById("js-test-result").innerHTML = "❌ Error: " + e.message;
    }
}

console.log("Diagnostic script loaded, showCancelModal type:", typeof showCancelModal);
</script>';

echo "\n</body></html>";
?>