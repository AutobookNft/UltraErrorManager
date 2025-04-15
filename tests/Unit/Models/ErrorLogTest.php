<?php

namespace Ultra\ErrorManager\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Models\ErrorLog; // Class under test
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Import ServiceProvider
use Ultra\ErrorManager\Tests\UltraTestCase;
use Illuminate\Support\Facades\DB; // Import DB Facade

/**
 * 📜 Oracode Unit Test: ErrorLogTest
 *
 * Tests the ErrorLog Eloquent model, focusing on mass assignment, attribute casting,
 * query scopes, and state management methods. Uses RefreshDatabase for DB interactions.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Models
 * @version         0.1.3 // Fixed date setting and coverage annotation.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the ErrorLog Eloquent model.
 * 🧱 Interacts with the in-memory database via Eloquent and RefreshDatabase.
 * 📡 Verifies database state and model attribute values.
 * 🧪 Focuses on model attributes, casts, scopes, and methods.
 */
#[CoversClass(ErrorLog::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)] // Added UsesClass
class ErrorLogTest extends UltraTestCase
{
    use RefreshDatabase;

    /**
     * Helper method to create a basic ErrorLog instance with default or overridden data.
     * @param array<string, mixed> $overrides
     * @return ErrorLog
     */
    private function createErrorLog(array $overrides = []): ErrorLog
    {
        $defaults = [
            'error_code' => 'DEFAULT_ERROR', 'type' => 'error', 'blocking' => 'not',
            'message' => 'Default dev message', 'user_message' => 'Default user message',
            'http_status_code' => 500, 'context' => ['key' => 'value'], 'display_mode' => 'div',
            'resolved' => false, 'notified' => false, 'created_at' => now(), 'updated_at' => now(),
        ];
        // Ensure 'created_at' uses a Carbon instance if overridden for safety
        if (isset($overrides['created_at']) && is_string($overrides['created_at'])) {
            $overrides['created_at'] = Carbon::parse($overrides['created_at']);
        }
         if (isset($overrides['updated_at']) && is_string($overrides['updated_at'])) {
            $overrides['updated_at'] = Carbon::parse($overrides['updated_at']);
        }
        return ErrorLog::create(array_merge($defaults, $overrides));
    }

    /**
     * 🎯 Test [Mass Assignment]: Verifies all fillable attributes can be mass assigned.
     * 🧪 Strategy: Create model using ::create() with all fillable fields.
     */
    #[Test]
    public function attributes_are_mass_assignable(): void
    {
        $now = now();
        $fillableData = [
            'error_code' => 'MASS_ASSIGN_TEST', 'type' => 'critical', 'blocking' => 'blocking',
            'message' => 'Dev message fillable', 'user_message' => 'User message fillable',
            'http_status_code' => 503, 'context' => ['test' => true, 'id' => 1], 'display_mode' => 'sweet-alert',
            'exception_class' => 'Test\\Exception', 'exception_message' => 'Exception message',
            'exception_code' => 123, 'exception_file' => '/path/to/file.php', 'exception_line' => 42,
            'exception_trace' => 'Trace line 1', 'request_method' => 'POST', 'request_url' => '/test/url',
            'user_agent' => 'TestAgent/2.0', 'ip_address' => '192.168.1.1', 'user_id' => 999,
            'resolved' => true, 'resolved_at' => $now, 'resolved_by' => 'Test Resolver',
            'resolution_notes' => 'Resolved notes.', 'notified' => true,
        ];

        $log = ErrorLog::create($fillableData);

        $this->assertInstanceOf(ErrorLog::class, $log);
        $this->assertTrue($log->exists);
        $this->assertEquals('MASS_ASSIGN_TEST', $log->error_code);
        $this->assertEquals(['test' => true, 'id' => 1], $log->context);
        $this->assertTrue($log->resolved);
    }

    /**
     * 🎯 Test [Casts]: Verifies attribute casting works correctly.
     * 🧪 Strategy: Create model, save, retrieve, assert types (array, boolean, datetime, integer).
     */
    #[Test]
    public function casts_handle_types_correctly(): void
    {
        $now = Carbon::parse('2024-03-15 10:30:00');
        $log = $this->createErrorLog([
            'context' => ['data' => ['nested' => 1]], 'resolved' => true, 'resolved_at' => $now,
            'notified' => false, 'http_status_code' => 418, 'exception_line' => 101,
            'user_id' => 777, 'created_at' => $now->copy()->subHour(), 'updated_at' => $now->copy()->subMinute(),
            'exception_code' => 50,
        ]);

        $retrievedLog = ErrorLog::find($log->id);

        $this->assertIsArray($retrievedLog->context);
        $this->assertEquals(['data' => ['nested' => 1]], $retrievedLog->context);
        $this->assertIsBool($retrievedLog->resolved);
        $this->assertTrue($retrievedLog->resolved);
        $this->assertIsBool($retrievedLog->notified);
        $this->assertFalse($retrievedLog->notified);
        $this->assertInstanceOf(Carbon::class, $retrievedLog->resolved_at);
        $this->assertEquals($now->timestamp, $retrievedLog->resolved_at->timestamp);
        $this->assertInstanceOf(Carbon::class, $retrievedLog->created_at);
        $this->assertInstanceOf(Carbon::class, $retrievedLog->updated_at);
        $this->assertIsInt($retrievedLog->http_status_code);
        $this->assertEquals(418, $retrievedLog->http_status_code);
        $this->assertIsInt($retrievedLog->exception_line);
        $this->assertEquals(101, $retrievedLog->exception_line);
        $this->assertIsInt($retrievedLog->user_id);
        $this->assertEquals(777, $retrievedLog->user_id);
        $this->assertIsInt($retrievedLog->exception_code);
        $this->assertEquals(50, $retrievedLog->exception_code);
    }

        /**
     * 🎯 Test [Scopes]: Verifies simple query scopes filter correctly.
     * 🧪 Strategy: Create minimal data, apply scopes (unresolved, resolved, critical, ofType, withCode), assert correct IDs returned.
     * NOTE: Date scopes (occurredAfter, occurredBefore) are deferred to Integration/Feature tests due to inconsistencies with SQLite in-memory date handling.
     */
    #[Test]
    public function scopes_filter_correctly(): void
    {
        // Arrange: Create minimal data set
        $logResolved = $this->createErrorLog(['resolved' => true, 'type' => 'notice', 'error_code' => 'RESOLVED_NOTICE']);
        $logUnresolvedCrit = $this->createErrorLog(['resolved' => false, 'type' => 'critical', 'error_code' => 'UNRESOLVED_CRIT']);
        $logUnresolvedWarn = $this->createErrorLog(['resolved' => false, 'type' => 'warning', 'error_code' => 'UNRESOLVED_WARN']);

        // --- Assert Scopes ---

        // Assert: scopeUnresolved
        $unresolved = ErrorLog::unresolved()->pluck('id');
        $this->assertCount(2, $unresolved);
        $this->assertContains($logUnresolvedCrit->id, $unresolved->all());
        $this->assertContains($logUnresolvedWarn->id, $unresolved->all());
        $this->assertNotContains($logResolved->id, $unresolved->all());

        // Assert: scopeResolved
        $resolved = ErrorLog::resolved()->pluck('id');
        $this->assertCount(1, $resolved);
        $this->assertEquals($logResolved->id, $resolved->first());
        $this->assertNotContains($logUnresolvedCrit->id, $resolved->all());

        // Assert: scopeCritical
        $critical = ErrorLog::critical()->pluck('id');
        $this->assertCount(1, $critical);
        $this->assertEquals($logUnresolvedCrit->id, $critical->first());

        // Assert: scopeOfType
        $warnings = ErrorLog::ofType('warning')->pluck('id');
        $this->assertCount(1, $warnings);
        $this->assertEquals($logUnresolvedWarn->id, $warnings->first());
        $notices = ErrorLog::ofType('notice')->pluck('id');
        $this->assertCount(1, $notices);
        $this->assertEquals($logResolved->id, $notices->first());

        // Assert: scopeWithCode
        $withCodeNotice = ErrorLog::withCode('RESOLVED_NOTICE')->pluck('id');
        $this->assertCount(1, $withCodeNotice);
        $this->assertEquals($logResolved->id, $withCodeNotice->first());
        $withCodeCrit = ErrorLog::withCode('UNRESOLVED_CRIT')->pluck('id');
        $this->assertCount(1, $withCodeCrit);
        $this->assertEquals($logUnresolvedCrit->id, $withCodeCrit->first());
    }

    /**
     * 🎯 Test [markAsResolved]: Sets attributes correctly and saves to DB.
     * 🧪 Strategy: Create log, call markAsResolved, assert database state and model state.
     */
    #[Test]
    public function markAsResolved_sets_attributes_and_saves(): void
    {
        $log = $this->createErrorLog(['resolved' => false, 'resolved_at' => null, 'resolved_by' => null, 'resolution_notes' => null]);
        $resolver = 'Padmin';
        $notes = 'Fixed the config.';

        $result = $log->markAsResolved($resolver, $notes);

        $this->assertTrue($result);
        $this->assertDatabaseHas('error_logs', [
            'id' => $log->id, 'resolved' => true,
            'resolved_by' => $resolver, 'resolution_notes' => $notes,
        ]);
        $dbLog = ErrorLog::find($log->id);
        $this->assertNotNull($dbLog->resolved_at);
        $this->assertInstanceOf(Carbon::class, $dbLog->resolved_at);
        $log->refresh();
        $this->assertTrue($log->resolved);
        $this->assertNotNull($log->resolved_at);
        $this->assertEquals($resolver, $log->resolved_by);
        $this->assertEquals($notes, $log->resolution_notes);
    }

    /**
     * 🎯 Test [markAsUnresolved]: Clears attributes correctly and saves to DB.
     * 🧪 Strategy: Create resolved log, call markAsUnresolved, assert database state and model state.
     */
    #[Test]
    public function markAsUnresolved_clears_attributes_and_saves(): void
    {
        $log = $this->createErrorLog(['resolved' => true, 'resolved_at' => now(), 'resolved_by' => 'Tester', 'resolution_notes' => 'Initial resolve.']);

        $result = $log->markAsUnresolved();

        $this->assertTrue($result);
        $this->assertDatabaseHas('error_logs', [
            'id' => $log->id, 'resolved' => false,
            'resolved_at' => null, 'resolved_by' => null, 'resolution_notes' => null,
        ]);
        $log->refresh();
        $this->assertFalse($log->resolved);
        $this->assertNull($log->resolved_at);
        $this->assertNull($log->resolved_by);
        $this->assertNull($log->resolution_notes);
    }

    /**
     * 🎯 Test [markAsNotified]: Sets notified flag correctly and saves to DB.
     * 🧪 Strategy: Create log with notified=false, call markAsNotified, assert database state and model state.
     */
    #[Test]
    public function markAsNotified_sets_flag_and_saves(): void
    {
        $log = $this->createErrorLog(['notified' => false]);

        $result = $log->markAsNotified();

        $this->assertTrue($result);
        $this->assertDatabaseHas('error_logs', ['id' => $log->id, 'notified' => true]);
        $log->refresh();
        $this->assertTrue($log->notified);
    }

    /**
     * 🎯 Test [getContextSummary]: Truncates long context correctly.
     * 🧪 Strategy: Create log with long context, call getContextSummary, verify truncation.
     */
    #[Test]
    public function getContextSummary_truncates_correctly(): void
    {
        $maxLength = 50;
        $longContext = ['a' => str_repeat('x', 100), 'b' => str_repeat('y', 50)];
        $log = new ErrorLog(['context' => $longContext]); // Instantiated only, no DB needed
        $expectedStart = '{"a":"';

        $summary = $log->getContextSummary($maxLength);

        $this->assertStringStartsWith($expectedStart, $summary);
        $this->assertStringEndsWith('...', $summary);
        $this->assertTrue(mb_strlen($summary) <= $maxLength);
    }

    /**
     * 🎯 Test [getContextSummary]: Returns full context when short.
     * 🧪 Strategy: Create log with short context, call getContextSummary, verify full JSON returned.
     */
    #[Test]
    public function getContextSummary_returns_full_when_short(): void
    {
        $maxLength = 100;
        $shortContext = ['id' => 123, 'status' => 'ok'];
        $log = new ErrorLog(['context' => $shortContext]);
        $expectedJson = json_encode($shortContext);

        $summary = $log->getContextSummary($maxLength);

        $this->assertEquals($expectedJson, $summary);
    }

    /**
     * 🎯 Test [getContextSummary]: Handles empty or null context.
     * 🧪 Strategy: Create log with empty/null context, verify specific string returned.
     */
    #[Test]
    public function getContextSummary_handles_empty_or_null_context(): void
    {
        $logEmpty = new ErrorLog(['context' => []]);
        $logNull = new ErrorLog(['context' => null]);
        $expectedOutput = 'No context available';

        $this->assertEquals($expectedOutput, $logEmpty->getContextSummary());
        $this->assertEquals($expectedOutput, $logNull->getContextSummary());
    }

    // NOTE: Tests for relationships (user()) and complex static queries
    // (getSimilarErrors(), getErrorFrequency(), getTopErrorCodes()) are deferred for MVP.

} // End class ErrorLogTest