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
 * Course filter based on course id number
 *
 * File         couponcohortid.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   1999 Martin Dougiamas  http://dougiamas.com
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace block_coupon\filters;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * block_coupon\filters\couponcohortid
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class couponcohortid extends \user_filter_type {
    /** @var string */
    protected $fieldid;

    /**
     * Constructor
     * @param boolean $advanced advanced form element flag
     * @param string $fieldid identifier for the field in the query
     */
    public function __construct($advanced, $fieldid = 'id') {
        $this->fieldid = $fieldid;
        parent::__construct('couponcohortid', get_string('cohort', 'core_cohort'), $advanced);
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    public function get_operators() {
        return array(0 => get_string('contains', 'filters'),
                     1 => get_string('doesnotcontain', 'filters'),
                     2 => get_string('isequalto', 'filters'),
                     3 => get_string('startswith', 'filters'),
                     4 => get_string('endswith', 'filters'),
                     5 => get_string('isempty', 'filters'));
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    public function get_fieldsselect() {
        return array('idnumber' => get_string('idnumber'),
                     'name' => get_string('name', 'core_cohort'),
            );
    }

    /**
     * Adds controls specific to this filter in the form.
     *
     * We modified this method to comply to Moodle standards.
     * This does not matter since our handler is also internal.
     *
     * @param object $mform a MoodleForm object to setup
     */
    public function setup_form(&$mform) {
        $objs = array();
        $objs['fieldselect'] = $mform->createElement('select', $this->_name.'_fld', null, $this->get_fieldsselect());
        $objs['select'] = $mform->createElement('select', $this->_name.'_op', null, $this->get_operators());
        $objs['text'] = $mform->createElement('text', $this->_name, null);
        $objs['select']->setLabel(get_string('limiterfor', 'filters', $this->_label));
        $objs['text']->setLabel(get_string('valuefor', 'filters', $this->_label));
        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        // ID Number for cohorts has PARAM_RAW.
        $mform->setType($this->_name, PARAM_RAW);
        $mform->disabledIf($this->_name, $this->_name.'_op', 'eq', 5);
        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
        $mform->setDefault($this->_name.'_op', 2);
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field    = $this->_name;
        $operator = $field.'_op';
        $selectfield = $field.'_fld';

        if (array_key_exists($operator, $formdata) && array_key_exists($selectfield, $formdata)) {
            if ($formdata->$operator != 5 && $formdata->$field == '') {
                // No data - no change except for empty filter.
                return false;
            }
            // If field value is set then use it, else it's null.
            $fieldvalue = null;
            if (isset($formdata->$field)) {
                $fieldvalue = $formdata->$field;
            }
            return array('operator' => (int)$formdata->$operator, 'value' => $fieldvalue, 'field' => $formdata->$selectfield);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array sql string and $params
     */
    public function get_sql_filter($data) {
        global $DB;
        static $counter = 0;
        $name = 'ex_couponcohortid'.$counter++;

        $operator = $data['operator'];
        $value    = $data['value'];
        $field    = $data['field'];

        $params = array();

        if ($value === '') {
            return '';
        }

        // If field is NOT the idnumber, clean using PARAM_TEXT.
        if ($field <> 'idnumber') {
            $value = clean_param($value, PARAM_TEXT);
        }

        $not = '';
        switch($operator) {
            case 0: // Contains.
                $res = $DB->sql_like('c.' . $field, ":$name", false, false);
                $params[$name] = "%$value%";
                break;
            case 1: // Does not contain.
                $not = 'NOT';
                $res = $DB->sql_like('c.' . $field, ":$name", false, false);
                $params[$name] = "%$value%";
                break;
            case 2: // Equal to.
                $res = $DB->sql_like('c.' . $field, ":$name", false, false);
                $params[$name] = "$value";
                break;
            case 3: // Starts with.
                $res = $DB->sql_like('c.' . $field, ":$name", false, false);
                $params[$name] = "$value%";
                break;
            case 4: // Ends with.
                $res = $DB->sql_like('c.' . $field, ":$name", false, false);
                $params[$name] = "%$value";
                break;
            case 5: // Empty.
                $not = 'NOT';
                $res = '(c.' . $field . ' IS NOT NULL AND c.' . $field . ' <> :'.$name.')';
                $params[$name] = '';
                break;
            default:
                return '';
        }

        $sql = "{$this->fieldid} $not IN (SELECT couponid
                         FROM {block_coupon_cohorts} cc
                         JOIN {cohort} c ON cc.cohortid=c.id
                         WHERE $res)";

        return array($sql, $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $field     = $data['field'];
        $operators = $this->get_operators();

        $a = new \stdClass();
        $a->label    = $this->_label . '.' . $field;
        $a->value    = '"'.s($value).'"';
        $a->operator = $operators[$operator];

        switch ($operator) {
            case 0: // Contains.
            case 1: // Doesn't contain.
            case 2: // Equal to.
            case 3: // Starts with.
            case 4: // Ends with.
                return get_string('textlabel', 'filters', $a);
            case 5: // Empty.
                return get_string('textlabelnovalue', 'filters', $a);
        }

        return '';
    }
}
