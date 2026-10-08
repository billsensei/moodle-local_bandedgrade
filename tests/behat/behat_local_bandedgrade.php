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

/**
 * Behat steps for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_bandedgrade extends behat_base {
    /**
     * Page URLs for "I am on the ... page": the type "Recalculate" takes the quiz name.
     *
     * Example: I am on the "Quiz 1" "local_bandedgrade > Recalculate" page
     *
     * @param string $type Page type.
     * @param string $identifier Quiz name.
     * @return moodle_url The URL.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        if (strtolower($type) !== 'recalculate') {
            throw new Exception('Unknown local_bandedgrade page type: ' . $type);
        }
        $cm = $this->get_cm_by_activity_name('quiz', $identifier);
        return new moodle_url('/local/bandedgrade/recalculate.php', ['cmid' => $cm->id]);
    }

    /**
     * Open the recalculate page of a quiz, check it is refused, then leave the error page.
     *
     * Behat fails any step that ends on an exception page, so the check and the navigation away are one step.
     *
     * @Then /^I should be refused the recalculate page of "(?P<quizname>[^"]*)"$/
     * @param string $quizname Quiz name.
     */
    public function i_should_be_refused_the_recalculate_page(string $quizname): void {
        $url = $this->resolve_page_instance_url('recalculate', $quizname);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $error = $this->getSession()->getPage()->find('xpath', "//div[@data-rel='fatalerror']");
        $errortext = $error ? $error->getText() : '';
        $shown = str_contains($this->getSession()->getPage()->getText(), 'What should happen to scores changed by hand?');
        $this->getSession()->visit($this->locate_path('/'));
        if (!str_contains($errortext, 'Sorry, but you do not currently have permissions to do that')) {
            throw new ExpectationException('Expected a "no permissions" error', $this->getSession());
        }
        if ($shown) {
            throw new ExpectationException('The recalculate page was shown to a refused user', $this->getSession());
        }
    }
}
