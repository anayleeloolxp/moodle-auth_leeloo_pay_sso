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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

/**
 * Plugin to sync users to Leeloo LXP Vendor account of the Moodle Admin
 */
class auth_plugin_leeloo_pay_sso extends auth_plugin_base {

    /**
     * Constructor
     */
    public function __construct() {
        $this->authtype = 'leeloo_pay_sso';
        $this->config = get_config('auth_leeloo_pay_sso');
    }

    /**
     * Generate random string.
     *
     * @param int $strength is strength
     * @return string $randomstring is random string
     */
    public function generate_string($strength = 16) {
        $input = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $inputlength = strlen($input);
        $randomstring = '';
        for ($i = 0; $i < $strength; $i++) {
            $randomcharacter = $input[mt_rand(0, $inputlength - 1)];
            $randomstring .= $randomcharacter;
        }

        return $randomstring;
    }

    /**
     * Check if user authenticated
     *
     * @param string $user The userdata
     * @param string $username The username
     * @param string $password The password
     * @return bool Return true
     */
    public function user_authenticated_hook(&$user, $username, $password) {

        setcookie('jsession_id', '', time() + (86400), "/");
        setcookie('license_key', '', time() + (86400), "/");

        global $CFG;

        setcookie('moodleurl', $CFG->wwwroot, time() + (86400), "/");

        $admins = get_admins();
        $isadmin = false;
        foreach ($admins as $admin) {
            if ($user->id == $admin->id) {
                $isadmin = true;
                break;
            }
        }
        if ($isadmin) {
            return true;
        }

        $useremail = $user->email;

        $license = $this->config->license;

        global $CFG;
        require_once($CFG->dirroot . '/lib/filelib.php');

        $postdata = array('license_key' => $license);
        $url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => false,
            'CURLOPT_POST' => count($postdata),
        );

        if (!$output = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $infoteamnio = json_decode($output);
        if ($infoteamnio->status != 'false') {
            $teamniourl = $infoteamnio->data->install_url;
        } else {
            return true;
        }

        $url = $teamniourl . '/admin/sync_moodle_course/check_user_by_email/' . base64_encode($useremail);
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => false,
            'CURLOPT_POST' => count($postdata),
            'CURLOPT_HTTPHEADER' => array(
                'LeelooLXPToken: ' . get_config('local_leeloolxpapi')->leelooapitoken . ''
            )
        );
        if (!$output = $curl->post($url, $postdata, $options)) {
            return true;
        }

        if ($output == '0') {
            return true;
        }

        $url = $teamniourl . '/admin/sync_moodle_course/check_user_status_by_email/' . base64_encode($useremail);
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => false,
            'CURLOPT_POST' => count($postdata),
            'CURLOPT_HTTPHEADER' => array(
                'LeelooLXPToken: ' . get_config('local_leeloolxpapi')->leelooapitoken . ''
            )
        );
        if (!$userstatusonteamnio = $curl->post($url, $postdata, $options)) {
            return true;
        }
        if ($userstatusonteamnio == 0) {
            return true;
        }

        $username = $username;
        $password = $this->generate_string(8);
        $useremail = $user->email;

        $leeloousername = $username;
        $leelooemail = $useremail;

        $postdata = array(
            'username' => base64_encode($leeloousername),
            'password' => $password,
            'email' => base64_encode($leelooemail),
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
        );

        $url = 'https://leeloolxp.com/api-leeloo/post/user/register';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
        );

        if (!$response = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $postdata = array('username' => base64_encode($leeloousername), 'password' => $password);

        $url = 'https://leeloolxp.com/api-leeloo/post/user/changepass';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
        );

        if (!$response = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $leeloolicense = $this->config->vendorkey;

        $postdata = array('username' => base64_encode($leeloousername), 'password' => $password, 'leeloolicense' => $leeloolicense, 'siteid' => $user->id);

        $url = 'https://leeloolxp.com/api-leeloo/post/user/login';
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
        );

        if (!$response = $curl->post($url, $postdata, $options)) {
            return true;
        }

        $resposearr = json_decode($response);
        if (isset($resposearr->session_id) && isset($resposearr->session_id) != '') {
            global $SESSION;
            $SESSION->jsession_id = $resposearr->session_id;

            setcookie('jsession_id', $resposearr->session_id, time() + (86400), "/");
            setcookie('license_key', $license, time() + (86400), "/");

            global $DB;
            $checksql = "SELECT * FROM {auth_leeloolxp_tracking_sso} WHERE userid = ?";
            $ssourls = $DB->get_record_sql($checksql, [$user->id]);

            $jurl = 'https://leeloolxp.com/es-frame?session_id=' . $resposearr->session_id . '&leeloolxplicense=' . $license;

            if ($ssourls) {
                $sql = 'UPDATE {auth_leeloolxp_tracking_sso} SET jurl = ? WHERE userid = ?';
                $DB->execute($sql, [$jurl, $user->id]);
            } else {
                $sql = 'INSERT INTO {auth_leeloolxp_tracking_sso} (userid, jurl, leeloourl) VALUES (?, ?, ?)';
                $DB->execute($sql, [$user->id, $jurl, '']);
            }
        } else {
            setcookie('jsession_id', '', time() + (86400), "/");
            setcookie('license_key', '', time() + (86400), "/");
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
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Clear cookie on logout
     *
     * @param object $user is userdata
     */
    public function postlogout_hook($user) {
        setcookie('jsession_id', '', time() + (86400), "/");
        setcookie('license_key', '', time() + (86400), "/");
    }
}
