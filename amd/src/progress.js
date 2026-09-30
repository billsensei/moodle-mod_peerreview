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
 * Live progress for the peer review teacher report.
 *
 * Polls the mod_peerreview_get_progress web service and updates the table cells in place. Refreshing can be switched
 * off; the choice is stored as a user preference. Polling pauses while the browser tab is hidden and never overlaps
 * two requests.
 *
 * @module     mod_peerreview/progress
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import {setUserPreference} from 'core_user/repository';

const PREFERENCE = 'mod_peerreview_autorefresh';

/**
 * Write one row's figures into the table.
 *
 * @param {HTMLElement} root The report container.
 * @param {Object} row Progress figures for one student.
 */
const updateRow = (root, row) => {
    const tr = root.querySelector(`[data-region="peerreview-row"][data-userid="${row.userid}"]`);
    if (!tr) {
        return;
    }
    const set = (field, text) => {
        const cell = tr.querySelector(`[data-field="${field}"]`);
        if (cell && cell.textContent !== text) {
            cell.textContent = text;
        }
    };
    set('given', `${row.givendone} / ${row.giventotal}`);
    set('received', `${row.receiveddone} / ${row.receivedtotal}`);
    set('grade', row.grade);
    set('participation', row.participation < 0 ? '' : `${row.participation}%`);
};

/**
 * Initialise the live progress on the report page.
 */
export const init = () => {
    const root = document.querySelector('[data-region="peerreview-report"]');
    if (!root) {
        return;
    }
    const cmid = parseInt(root.dataset.cmid, 10);
    const groupid = parseInt(root.dataset.groupid, 10);
    const interval = parseInt(root.dataset.interval, 10) * 1000;
    const toggle = root.querySelector('[data-action="toggle-refresh"]');
    const refreshNow = root.querySelector('[data-action="refresh-now"]');
    const status = root.querySelector('[data-region="peerreview-status"]');

    let timer = null;
    let busy = false;

    const schedule = () => {
        window.clearTimeout(timer);
        if (toggle.checked) {
            timer = window.setTimeout(refresh, interval);
        }
    };

    const refresh = async() => {
        if (busy) {
            return;
        }
        if (document.hidden) {
            schedule(); // Skip this round; try again later.
            return;
        }
        busy = true;
        try {
            const data = await Ajax.call([{
                methodname: 'mod_peerreview_get_progress',
                args: {cmid, groupid},
            }])[0];
            data.rows.forEach((row) => updateRow(root, row));
            status.textContent = await getString('updatedat', 'mod_peerreview', new Date(data.time * 1000).toLocaleTimeString());
        } catch (error) {
            status.textContent = await getString('updatefailed', 'mod_peerreview');
        } finally {
            busy = false;
            schedule();
        }
    };

    toggle.addEventListener('change', () => {
        setUserPreference(PREFERENCE, toggle.checked ? 1 : 0);
        if (toggle.checked) {
            refresh();
        } else {
            window.clearTimeout(timer);
        }
    });
    refreshNow.addEventListener('click', () => {
        window.clearTimeout(timer);
        refresh();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && toggle.checked) {
            window.clearTimeout(timer);
            refresh();
        }
    });

    schedule();
};
