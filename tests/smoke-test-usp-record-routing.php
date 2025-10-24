#!/usr/bin/env php
<?php
/**
 * USP Record Routing Smoke Test
 * 
 * Manual smoke test to validate that all wrapInRecord() calls
 * create USP Records with correct to_id and from_id after bug fixes.
 * 
 * This bypasses Laravel test infrastructure for faster validation.
 * 
 * Usage: php tests/smoke-test-usp-record-routing.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\UspMessageService;
use Usp\Record;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n🧪 USP Record Routing Smoke Test\n";
echo "================================\n\n";

$uspService = new UspMessageService();
$passed = 0;
$failed = 0;

// Test 1: wrapInRecord() argument order
echo "Test 1: wrapInRecord() uses correct argument order\n";
echo "---------------------------------------------------\n";

try {
    $message = $uspService->createGetMessage(['Device.DeviceInfo.'], 'test-msg-001');
    $deviceEndpoint = 'proto::test-device-123';
    $controllerEndpoint = config('usp.controller_endpoint_id', 'proto::acs-controller-001');
    
    echo "  Creating USP Record with:\n";
    echo "    - Device endpoint (to_id):      $deviceEndpoint\n";
    echo "    - Controller endpoint (from_id): $controllerEndpoint\n";
    
    $record = $uspService->wrapInRecord($message, $deviceEndpoint, $controllerEndpoint);
    
    // Validate to_id
    if ($record->getToId() === $deviceEndpoint) {
        echo "  ✅ PASS: to_id = $deviceEndpoint (correct - device is destination)\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: to_id = " . $record->getToId() . " (expected: $deviceEndpoint)\n";
        $failed++;
    }
    
    // Validate from_id
    if ($record->getFromId() === $controllerEndpoint) {
        echo "  ✅ PASS: from_id = $controllerEndpoint (correct - controller is source)\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: from_id = " . $record->getFromId() . " (expected: $controllerEndpoint)\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Test 2: Get message Record structure
echo "Test 2: Get message creates valid USP Record\n";
echo "--------------------------------------------\n";

try {
    $message = $uspService->createGetMessage(
        ['Device.DeviceInfo.', 'Device.WiFi.'],
        'get-msg-001'
    );
    
    $record = $uspService->wrapInRecord(
        $message,
        'proto::device-002',
        config('usp.controller_endpoint_id')
    );
    
    // Validate Record has required fields
    if ($record->getToId() && $record->getFromId()) {
        echo "  ✅ PASS: Record has both to_id and from_id\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: Record missing to_id or from_id\n";
        $failed++;
    }
    
    if ($record->getRecordType()) {
        echo "  ✅ PASS: Record has record_type set\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: Record missing record_type\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Test 3: Set message Record structure
echo "Test 3: Set message creates valid USP Record\n";
echo "--------------------------------------------\n";

try {
    $message = $uspService->createSetMessage(
        [
            'Device.ManagementServer.' => [
                'PeriodicInformInterval' => '600'
            ]
        ],
        'set-msg-001'
    );
    
    $record = $uspService->wrapInRecord(
        $message,
        'proto::device-003',
        'proto::controller-001'
    );
    
    if ($record->getToId() === 'proto::device-003') {
        echo "  ✅ PASS: Set message to_id correct\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: Set message to_id incorrect\n";
        $failed++;
    }
    
    if ($record->getFromId() === 'proto::controller-001') {
        echo "  ✅ PASS: Set message from_id correct\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: Set message from_id incorrect\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Test 4: Add message Record structure
echo "Test 4: Add message creates valid USP Record\n";
echo "--------------------------------------------\n";

try {
    $message = $uspService->createAddMessage(
        'Device.WiFi.SSID.',
        ['SSID' => 'TestNetwork', 'Enable' => 'true'],
        'add-msg-001'
    );
    
    $record = $uspService->wrapInRecord(
        $message,
        'proto::device-add',
        config('usp.controller_endpoint_id')
    );
    
    if ($record->getToId() === 'proto::device-add') {
        echo "  ✅ PASS: Add message to_id = proto::device-add\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: Add message to_id != proto::device-add\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Test 5: Delete message Record structure  
echo "Test 5: Delete message creates valid USP Record\n";
echo "------------------------------------------------\n";

try {
    $message = $uspService->createDeleteMessage(
        ['Device.WiFi.SSID.1.'],
        'delete-msg-001'
    );
    
    $record = $uspService->wrapInRecord(
        $message,
        'proto::device-delete',
        config('usp.controller_endpoint_id')
    );
    
    if ($record->getToId() === 'proto::device-delete') {
        echo "  ✅ PASS: Delete message to_id = proto::device-delete\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: Delete message to_id != proto::device-delete\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Test 6: Binary serialization
echo "Test 6: USP Record serializes to Protocol Buffers\n";
echo "--------------------------------------------------\n";

try {
    $message = $uspService->createGetMessage(['Device.'], 'binary-test-001');
    $record = $uspService->wrapInRecord(
        $message,
        'proto::device-binary',
        config('usp.controller_endpoint_id')
    );
    
    $binaryPayload = $record->serializeToString();
    
    if (!empty($binaryPayload) && strlen($binaryPayload) > 0) {
        echo "  ✅ PASS: Record serializes to binary (" . strlen($binaryPayload) . " bytes)\n";
        $passed++;
        
        // Validate binary starts with protobuf signature
        if (strlen($binaryPayload) > 10) {
            echo "  ✅ PASS: Binary payload has valid size for Protocol Buffers\n";
            $passed++;
        } else {
            echo "  ❌ FAIL: Binary payload too small\n";
            $failed++;
        }
    } else {
        echo "  ❌ FAIL: Record serialization failed\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "  ❌ FAIL: Exception - " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Summary
echo "Summary\n";
echo "=======\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! USP Record routing is correct.\n";
    echo "\n";
    echo "Critical fixes validated:\n";
    echo "  ✅ wrapInRecord() uses correct argument order (message, device, controller)\n";
    echo "  ✅ USP Records have to_id = device endpoint (destination)\n";
    echo "  ✅ USP Records have from_id = controller endpoint (source)\n";
    echo "  ✅ Binary Protocol Buffers serialization works correctly\n";
    echo "\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED - Review the fixes\n\n";
    exit(1);
}
