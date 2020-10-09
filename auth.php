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
 * Main File for Auth
 *
 * @package auth_leeloo_pay_sso
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

/**
 * Plugin to sync users to Leeloo LXP Vendor account of the Moodle Admin
 */
class auth_plugin_leeloo_pay_sso extends auth_plugin_base {

    /**
     * Constructor
     */
    function __construct() {
        $this->authtype = 'leeloo_pay_sso';
        $this->config = get_config('leeloo_pay_sso');
    }

    /**
     * Check if user authenticated
     *
     * @param string $user The userdata
     * @param string $username The username
     * @param string $password The password
     * @return bool Return true
     */
    function user_authenticated_hook(&$user, $username, $password) {

        global $CFG;
        global $SITE;

        $site_prefix = str_ireplace('https://', '', $CFG->wwwroot);
        $site_prefix = str_ireplace('http://', '', $site_prefix);
        $site_prefix = str_ireplace('www.', '', $site_prefix);
        $site_prefix = str_ireplace('.', '_', $site_prefix);
        $site_prefix = str_ireplace('/', '_', $site_prefix);
        $site_prefix = $site_prefix . '_pre_';

        $username = $username;
        $password = $password;
        $user_email = $user->email;

        $leeloousername = $site_prefix . $username;
        $leelooemail = $site_prefix . $user_email;

        $postdata = array('username' => $leeloousername, 'password' => $password, 'email' => $leelooemail, 'firstname' => $user->firstname, 'lastname' => $user->lastname . ' (' . $SITE->fullname . ')');

        $url = 'https://leeloolxp.com/api-leeloo/post/user/register';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
        );

        if (!$response = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $postdata = array('username' => $leeloousername, 'password' => $password);

        $url = 'https://leeloolxp.com/api-leeloo/post/user/changepass';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
        );

        if (!$response = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $postdata = array('username' => $leeloousername, 'password' => $password);

        $url = 'https://leeloolxp.com/api-leeloo/post/user/login';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
        );

        if (!$response = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $respose_arr = json_decode($response);
        if (isset($respose_arr->session_id) && isset($respose_arr->session_id) != '') {
            global $SESSION;
            $SESSION->jsession_id = $respose_arr->session_id;
        }

        return true;
    }

    /**
     * Returns false if the user exists and the password is wrong.
     *
     * @param string $username is username
     * @param string $password is password
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        return false;
    }
}
?>