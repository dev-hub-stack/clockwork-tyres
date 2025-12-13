<?php

/**
 * Master Test Runner for Invoice Actions
 * 
 * Runs all invoice action tests in sequence:
 * 1. Start Processing
 * 2. Cancel Order
 * 3. Delete (optional)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "         INVOICE ACTIONS - MASTER TEST RUNNER\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "This script will run all invoice action tests in sequence:\n";
echo "  1. ✅ Start Processing Action\n";
echo "  2. ❌ Cancel Order Action\n";
echo "  3. 🗑️  Delete Action (optional)\n\n";

// Check if we should run delete test
$args = $argv ?? [];
$runDeleteTest = in_array('--with-delete', $args) || in_array('-d', $args);

if (!$runDeleteTest) {
    echo "ℹ️  Note: Delete test will be SKIPPED by default.\n";
    echo "   To include delete test, run: php test_all_invoice_actions.php --with-delete\n\n";
}

echo "Press Enter to continue or Ctrl+C to cancel...\n";
if (php_sapi_name() === 'cli') {
    // Only wait for input in CLI mode
    // fgets(STDIN);
}

$results = [];
$totalTests = 0;
$passedTests = 0;

// Helper function to run a test script
function runTest($scriptPath, $testName) {
    global $totalTests, $passedTests, $results;
    
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════════\n";
    echo "  Running: {$testName}\n";
    echo "═══════════════════════════════════════════════════════════════════════\n\n";
    
    $totalTests++;
    
    // Execute the test script
    $output = [];
    $returnCode = 0;
    exec("php \"{$scriptPath}\" 2>&1", $output, $returnCode);
    
    // Display output
    echo implode("\n", $output);
    echo "\n";
    
    $passed = ($returnCode === 0);
    
    if ($passed) {
        $passedTests++;
        $results[$testName] = '✅ PASSED';
    } else {
        $results[$testName] = '❌ FAILED';
    }
    
    return $passed;
}

// TEST 1: Start Processing
$test1Passed = runTest(
    __DIR__ . '/test_start_processing_action.php',
    'Test 1: Start Processing Action'
);

// Wait a bit between tests
sleep(2);

// TEST 2: Cancel Order
$test2Passed = runTest(
    __DIR__ . '/test_cancel_order_action.php',
    'Test 2: Cancel Order Action'
);

// Wait a bit between tests
sleep(2);

// TEST 3: Delete (optional)
$test3Passed = null;
if ($runDeleteTest) {
    echo "\n⚠️  WARNING: About to run DELETE test!\n";
    echo "   This will permanently delete a test invoice.\n";
    echo "   Press Enter to continue or Ctrl+C to skip...\n";
    if (php_sapi_name() === 'cli') {
        // fgets(STDIN);
    }
    
    $test3Passed = runTest(
        __DIR__ . '/test_delete_action.php',
        'Test 3: Delete Action'
    );
} else {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════════\n";
    echo "  Skipped: Test 3: Delete Action\n";
    echo "═══════════════════════════════════════════════════════════════════════\n";
    echo "\nℹ️  Delete test skipped (run with --with-delete to include)\n";
}

// Final Summary
echo "\n\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                    FINAL TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

foreach ($results as $testName => $result) {
    echo "{$result}  {$testName}\n";
}

if ($test3Passed === null) {
    echo "⏭️  SKIPPED  Test 3: Delete Action\n";
}

echo "\n";
echo "───────────────────────────────────────────────────────────────────────\n";
echo "Tests Run: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";

if ($test3Passed === null) {
    echo "Skipped: 1\n";
}

$allPassed = ($passedTests === $totalTests);

echo "───────────────────────────────────────────────────────────────────────\n\n";

if ($allPassed) {
    echo "🎉 ALL TESTS PASSED!\n\n";
    echo "Your invoice actions are working correctly:\n";
    echo "  ✅ Start Processing - Allocates inventory properly\n";
    echo "  ✅ Cancel Order - Deallocates inventory and keeps audit trail\n";
    if ($test3Passed === true) {
        echo "  ✅ Delete - Permanently removes records (use with caution!)\n";
    }
    echo "\n";
} else {
    echo "⚠️  SOME TESTS FAILED\n\n";
    echo "Review the test output above to identify issues.\n";
    echo "Check:\n";
    echo "  - OrderObserver is working (for inventory allocation)\n";
    echo "  - Database relationships are set up correctly\n";
    echo "  - Model events are firing properly\n\n";
}

echo "═══════════════════════════════════════════════════════════════════════\n\n";

// Recommendations
echo "📚 NEXT STEPS:\n";
echo "───────────────────────────────────────────────────────────────────────\n";
if ($allPassed) {
    echo "1. Test the actions in the Filament UI:\n";
    echo "   - Go to http://localhost:8000/admin/invoices\n";
    echo "   - Hover over action buttons to see tooltips\n";
    echo "   - Try Start Processing on a pending invoice\n";
    echo "   - Try Cancel Order on a processing invoice\n\n";
    
    echo "2. Read the documentation:\n";
    echo "   - INVOICE_ACTIONS_DOCUMENTATION.md (comprehensive guide)\n";
    echo "   - INVOICE_ACTIONS_SUMMARY.md (quick reference)\n\n";
    
    echo "3. Train your team:\n";
    echo "   - Use Cancel Order for real orders (not Delete!)\n";
    echo "   - Always provide cancellation reasons\n";
    echo "   - Review stock availability before processing\n\n";
} else {
    echo "1. Review the failed test output above\n";
    echo "2. Check if OrderObserver is registered in AppServiceProvider\n";
    echo "3. Verify database relationships in Order, OrderItem models\n";
    echo "4. Run tests again after fixes\n\n";
}

exit($allPassed ? 0 : 1);
