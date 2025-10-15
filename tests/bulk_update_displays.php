<?php
/**
 * Bulk Update Script for Public Display Files
 * Updates the remaining display files with new station list format
 */

// Files to update
$files = [
    'public_display_pharmacy.php' => 'Pharmacy',
    'public_display_billing.php' => 'Billing', 
    'public_display_document.php' => 'Document'
];

$base_path = 'C:\xampp\htdocs\wbhsms-cho-koronadal-1\pages\queueing\\';

// CSS styles to add
$css_addition = '
        /* Station List Styles */
        .stations-container {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .currently-called {
            background: linear-gradient(135deg, var(--warning-orange), #ffd700);
            color: var(--text-dark);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .currently-called h2 {
            margin: 0 0 1rem 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .currently-called .queue-code {
            font-size: 3rem;
            font-weight: 900;
            margin: 1rem 0;
        }

        .currently-called .station-info {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .stations-list {
            background: var(--text-light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .stations-header {
            background: var(--primary-blue);
            color: var(--text-light);
            padding: 1.5rem 2rem;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
        }

        .station-row {
            display: flex;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            transition: background-color 0.3s ease;
        }

        .station-row:last-child {
            border-bottom: none;
        }

        .station-row:hover {
            background: var(--accent-blue);
        }

        .station-row.active {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 5px solid var(--warning-orange);
        }

        .station-info {
            flex: 1;
        }

        .station-id {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .station-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .station-assignment {
            font-size: 1rem;
            color: var(--text-muted);
        }

        .queue-status {
            text-align: right;
            min-width: 200px;
        }

        .queue-code {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary-blue);
        }

        .queue-idle {
            font-size: 1.5rem;
            color: var(--text-muted);
            font-style: italic;
        }

        .queue-counts {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }
';

foreach ($files as $filename => $stationType) {
    $filePath = $base_path . $filename;
    echo "Processing: $filename for $stationType\n";
    
    // Add logic here to update files
    // (This would be used in a separate process)
}

echo "Script ready for manual updates.\n";
?>