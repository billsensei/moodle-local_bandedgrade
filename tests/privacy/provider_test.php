<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_bandedgrade\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    /** @var \stdClass Quiz. */
    private $quiz;

    /** @var \context_module Quiz context. */
    private $context;

    /** @var \stdClass First student. */
    private $one;

    /** @var \stdClass Second student. */
    private $two;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
        $this->quiz = $this->make_frog_quiz(2);
        $this->enable($this->quiz, [0 => 0, 1 => 1, 2 => 2]);
        $this->context = \context_module::instance($this->quiz->cmid);
        $this->one = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->two = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($this->quiz, $this->one, $this->frogs(1, 2));
        $this->attempt($this->quiz, $this->one, $this->frogs(2, 2));
        $this->attempt($this->quiz, $this->two, $this->frogs(1, 2));
    }

    /**
     * Rows per table for a user.
     *
     * @param int $userid User.
     * @return int[] Table => count.
     */
    private function rows(int $userid): array {
        global $DB;
        $counts = [];
        foreach (provider::USER_TABLES as $table) {
            $counts[$table] = $DB->count_records($table, ['userid' => $userid]);
        }
        return $counts;
    }

    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_bandedgrade'));
        $this->assertCount(3, $collection->get_collection());
    }

    public function test_contexts_and_users(): void {
        $contextids = provider::get_contexts_for_userid($this->one->id)->get_contextids();
        $this->assertSame([(int)$this->context->id], array_map('intval', $contextids));
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid($nobody->id)->get_contextids());

        $userlist = new userlist($this->context, 'local_bandedgrade');
        provider::get_users_in_context($userlist);
        $expected = [$this->one->id, $this->two->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function test_export(): void {
        $contextlist = new approved_contextlist($this->one, 'local_bandedgrade', [$this->context->id]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'local_bandedgrade')]);
        $this->assertCount(2, $data->attempts);
        $this->assertEquals(1, $data->attempts[0]->correctcount);
        $this->assertEquals(2, $data->attempts[1]->correctcount);
        $this->assertEquals(2, $data->lastscore);
    }

    public function test_delete_for_user(): void {
        provider::delete_data_for_user(new approved_contextlist($this->one, 'local_bandedgrade', [$this->context->id]));
        $this->assertSame(['local_bandedgrade_attempt' => 0, 'local_bandedgrade_written' => 0], $this->rows($this->one->id));
        $this->assertSame(['local_bandedgrade_attempt' => 1, 'local_bandedgrade_written' => 1], $this->rows($this->two->id));
    }

    public function test_delete_for_users(): void {
        provider::delete_data_for_users(new approved_userlist($this->context, 'local_bandedgrade', [$this->two->id]));
        $this->assertSame(['local_bandedgrade_attempt' => 0, 'local_bandedgrade_written' => 0], $this->rows($this->two->id));
        $this->assertSame(['local_bandedgrade_attempt' => 2, 'local_bandedgrade_written' => 1], $this->rows($this->one->id));
    }

    public function test_delete_all_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(['local_bandedgrade_attempt' => 0, 'local_bandedgrade_written' => 0], $this->rows($this->one->id));
        $this->assertSame(['local_bandedgrade_attempt' => 0, 'local_bandedgrade_written' => 0], $this->rows($this->two->id));

        // Another context type is ignored.
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->course->id));
    }
}
