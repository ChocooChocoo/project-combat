<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportReadOnlyTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    public function test_every_get_preserves_domain_rows_and_dispatches_no_writes(): void
    {
        $this->setUpDecisionSupport();
        $membership = $this->makeMembership($this->member, ['ends_on' => '2026-10-01']);
        $this->membershipEvent($membership, 'activated', '2026-09-01 10:00:00');
        $session = $this->occurrence();
        $this->markedBooking($session, null);
        $this->income('other', '123.45');
        $this->customerAt('2026-11-02 10:00:00');
        $this->payrollFixture('2026-11-05', lines: [['gross_pay' => '100.00', 'net_pay' => '90.00']]);
        $tables = array_values(array_diff(Schema::getTableListing(), ['users', 'personal_access_tokens', 'sessions']));
        $snapshot = function () use ($tables): array {
            $rows = [];
            foreach ($tables as $table) {
                $values = DB::table($table)->get()->map(fn ($row) => json_encode($row))->all();
                sort($values);
                $rows[$table] = $values;
            }

            return $rows;
        };
        $before = $snapshot();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        foreach (['options', 'attendance', 'memberships', 'athletes', 'services', 'customers', 'employees', 'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability'] as $view) {
            $this->actingAs($this->owner)->getJson($this->decisionUrl($view).'?from=2026-11-01&to=2026-11-07')->assertOk();
        }
        $this->assertSame([], $writes);
        $this->assertSame($before, $snapshot());
    }
}
