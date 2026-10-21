<?php
namespace mod_fredkicourseimport;
final class token_manager_test extends \advanced_testcase {
    public function test_missing_token_returns_null(): void {
        $this->resetAfterTest();
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get(999, 999));
    }
}
