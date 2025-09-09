<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_easycrm.class.php
 * \ingroup easycrm
 * \brief   EasyCRM hook overload.
 */

/**
 * Class ActionsEasycrm
 */
class ActionsEasycrm
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param  array  $parameters Hook metadatas (context, etc...)
     * @param  object $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param  string $action     Current action (if set). Generally create or edit or null
     * @param  object $hookmanager Hook manager propagated to allow calling another hook
     * @return int                 < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
            // do something only for the context 'somecontext1' or 'somecontext2'
        }

        if (!$error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Overloading the printCommonFooter function : replacing the parent's function with the one below
     *
     * @param  array  $parameters Hook metadatas (context, etc...)
     * @param  object $object     The object to process
     * @param  string $action     Current action (if set). Generally create or edit or null
     * @param  object $hookmanager Hook manager propagated to allow calling another hook
     * @return int                 < 0 on error, 0 on success, 1 to replace standard code
     */
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter
if (empty($conf->global->EASYCRM_CALL_NOTIFICATIONS_DISABLED) && !empty($user->id)) {
    $url = dol_buildpath('/custom/easycrm/js/call_notifications.js.php', 1);
} ?>
<?php if (!empty($url)) { ?>
<script type="text/javascript" src="<?= dol_escape_htmltag($url) ?>"></script>
<?php }


        if (!$error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param  array  $parameters Hook metadatas (context, etc...)
     * @param  object $object     The object to process
     * @param  string $action     Current action (if set). Generally create or edit or null
     * @param  object $hookmanager Hook manager propagated to allow calling another hook
     * @return int                 < 0 on error, 0 on success, 1 to replace standard code
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
            // do something only for the context 'somecontext1' or 'somecontext2'
        }

        if (!$error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }
}
