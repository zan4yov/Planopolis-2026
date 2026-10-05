<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_planopolis;

use core\hook\navigation\primary_extend;
use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks for local_planopolis.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var string[] quiz pages that get the protection. */
    const PROTECTED_PAGES = ['mod-quiz-attempt', 'mod-quiz-summary', 'mod-quiz-review'];

    /**
     * Add the anti-copy / anti-screenshot layer and the personal watermark to quiz attempt pages.
     *
     * A web page cannot fully stop screenshots (a phone camera always works), so this layer:
     * blocks selecting/copying/printing, hides the questions whenever the participant leaves the
     * window (e.g. to open a snipping tool), logs those events for the committee, and stamps every
     * page with the participant's name so any leaked picture identifies its source.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE, $USER;

        if (during_initial_install() || !get_config('local_planopolis', 'protection')) {
            return;
        }
        if (!in_array($PAGE->pagetype, self::PROTECTED_PAGES, true) || !$PAGE->cm || !isloggedin()) {
            return;
        }
        if (has_capability('local/planopolis:bypassprotection', $PAGE->context)) {
            return;
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        $config = [
            'attempt' => $attemptid,
            'log' => $PAGE->pagetype !== 'mod-quiz-review' && $attemptid > 0,
            'logurl' => (new \core\url('/local/planopolis/log.php'))->out(false),
            'sesskey' => sesskey(),
            'watermark' => (bool) get_config('local_planopolis', 'watermark'),
            'mark' => \local_planopolis\local\helper::display_name($USER) . ' · ' . $USER->username,
            'leftmsg' => get_string('protect_left', 'local_planopolis'),
            'blockedmsg' => get_string('protect_blocked', 'local_planopolis'),
        ];
        $hook->add_html(self::protection_html($config));
    }

    /**
     * The CSS and JavaScript of the protection layer.
     *
     * @param array $config
     * @return string
     */
    protected static function protection_html(array $config): string {
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        return <<<HTML
<style id="planopolis-protect-css">
  #page-content, #page-content * { -webkit-user-select: none !important; user-select: none !important;
    -webkit-touch-callout: none !important; }
  #page-content input[type=text], #page-content textarea { -webkit-user-select: text !important; user-select: text !important; }
  #page-content img, #page-content audio { -webkit-user-drag: none; pointer-events: auto; }
  body.planopolis-hidden #page { filter: blur(24px) !important; }
  #planopolis-shield { position: fixed; inset: 0; z-index: 100000; display: none; align-items: center; justify-content: center;
    background: rgba(15, 23, 42, .88); color: #fff; font-size: 1.25rem; text-align: center; padding: 2rem; }
  body.planopolis-hidden #planopolis-shield { display: flex; }
  #planopolis-watermark { position: fixed; inset: 0; z-index: 99999; pointer-events: none; opacity: .09; }
  #planopolis-toast { position: fixed; left: 50%; bottom: 1.5rem; transform: translateX(-50%); z-index: 100001;
    background: #b91c1c; color: #fff; padding: .6rem 1rem; border-radius: .4rem; display: none; }
  @media print { html, body { display: none !important; } }
</style>
<script>
(function() {
  var cfg = {$json};
  var lastLog = {};
  function log(type) {
    if (!cfg.log) { return; }
    var now = Date.now();
    if (lastLog[type] && now - lastLog[type] < 3000) { return; }
    lastLog[type] = now;
    var data = new FormData();
    data.append('attempt', cfg.attempt); data.append('type', type); data.append('sesskey', cfg.sesskey);
    if (navigator.sendBeacon) { navigator.sendBeacon(cfg.logurl, data); }
    else { fetch(cfg.logurl, {method: 'POST', body: data, keepalive: true, credentials: 'same-origin'}); }
  }
  function toast() {
    var t = document.getElementById('planopolis-toast');
    if (!t) { return; }
    t.textContent = cfg.blockedmsg; t.style.display = 'block';
    clearTimeout(t._h); t._h = setTimeout(function() { t.style.display = 'none'; }, 2500);
  }
  function hide() { document.body.classList.add('planopolis-hidden'); log('leave'); }
  function show() { document.body.classList.remove('planopolis-hidden'); }
  function block(e, type) { e.preventDefault(); e.stopPropagation(); toast(); if (type) { log(type); } return false; }

  ['copy', 'cut', 'contextmenu', 'dragstart', 'selectstart'].forEach(function(ev) {
    document.addEventListener(ev, function(e) {
      var t = e.target;
      if (ev === 'selectstart' && t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA')) { return; }
      block(e, ev === 'copy' || ev === 'cut' ? 'copy' : null);
    }, true);
  });
  document.addEventListener('keydown', function(e) {
    var k = (e.key || '').toLowerCase();
    var mod = e.ctrlKey || e.metaKey;
    if (k === 'printscreen' || k === 'f12' || (mod && ['c', 'x', 'a', 'p', 's', 'u'].indexOf(k) >= 0)
        || (mod && e.shiftKey && ['i', 'j', 'c', 's'].indexOf(k) >= 0)) {
      return block(e, k === 'printscreen' || (mod && e.shiftKey && k === 's') ? 'screenshot' : 'shortcut');
    }
  }, true);
  document.addEventListener('keyup', function(e) {
    if ((e.key || '').toLowerCase() === 'printscreen') {
      try { navigator.clipboard.writeText(''); } catch (err) { /* ignore */ }
      log('screenshot'); toast();
    }
  }, true);
  window.addEventListener('beforeprint', function() { log('print'); });
  window.addEventListener('blur', hide);
  window.addEventListener('focus', show);
  document.addEventListener('visibilitychange', function() { if (document.hidden) { hide(); } else { show(); } });

  document.addEventListener('DOMContentLoaded', function() {
    var shield = document.createElement('div');
    shield.id = 'planopolis-shield'; shield.textContent = cfg.leftmsg;
    shield.addEventListener('click', show);
    document.body.appendChild(shield);
    var t = document.createElement('div'); t.id = 'planopolis-toast'; document.body.appendChild(t);
    if (cfg.watermark) {
      var text = cfg.mark.replace(/[&<>"']/g, function(c) { return '&#' + c.charCodeAt(0) + ';'; });
      var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="220">'
        + '<text x="10" y="120" transform="rotate(-25 210 110)" font-family="Arial" font-size="18" fill="#000">'
        + text + '</text></svg>';
      var wm = document.createElement('div');
      wm.id = 'planopolis-watermark';
      wm.style.backgroundImage = 'url("data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg) + '")';
      document.body.appendChild(wm);
    }
  });
})();
</script>
HTML;
    }

    /**
     * Add a "Planopolis panel" item to the primary navigation for admins of the competition.
     *
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        $courseid = (int) get_config('local_planopolis', 'courseid');
        if (!$courseid || !\core\context\course::instance($courseid, IGNORE_MISSING)) {
            return;
        }
        if (!has_capability('local/planopolis:manage', \core\context\course::instance($courseid))) {
            return;
        }
        $hook->get_primaryview()->add(get_string('panel', 'local_planopolis'),
            new \core\url('/local/planopolis/index.php'), \navigation_node::TYPE_CUSTOM, null, 'local_planopolis');
    }
}
