<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

include_once("./Services/LDAP/classes/class.ilLDAPPlugin.php");

/**
 * This Plugin is not actually used to assign or deassign Roles,
 * but to check the courses' metadata keyword,
 * and add users whos 'departement' attribute (in the LDAP) matches this keyword to theese courses.
 *
 * @author   Fabian Schmid <fs@studer-raimann.ch>
 * @author   Gabriel Comte <gc@studer-raimann.ch>
 * @version  $Id$
 * @internal 2.0.0a
 * uses Plugin-Hook
 *
 */
class ilRoleAssignmentPlugin extends ilLDAPPlugin implements ilLDAPRoleAssignmentPlugin {

	const COURSE_KEYWORD_PREFIX = 'LDAP_';
	const LDAP_DEPARTEMENT_PREFIX = 'Class_';
	/**
	 * @var array
	 */
	protected static $course_ref_id_cache = array();


	/**
	 * @return string
	 */
	public function getPluginName() {
		return 'RoleAssignment';
	}


	/**
	 * check role assignment for a specific plugin id
	 * (defined in the shibboleth role assignment administration).
	 *
	 * @param int $a_plugin_id   Unique plugin id
	 * @param array $a_user_data Array with user data ($_SERVER)
	 * @return bool false -> we don't want to add any role
	 */
	public function checkRoleAssignment($a_plugin_id, $a_user_data) {
		require_once('./Modules/Course/classes/class.ilObjCourse.php');

		global $ilLog;
		/**
		 * @var $ilLog ilLog
		 */

		//Only do something if the LDAP-Attribute 'departement' starts with 'Class_'
		if (substr($a_user_data['department'], 0, 6) == self::LDAP_DEPARTEMENT_PREFIX) {

			//get the courses ref_id
			$ref_id = self::getCrsRefIdByMetaKeyword($a_user_data['department']);

			//get the users id
			$user_id = ilObjUser::getUserIdByLogin($a_user_data['ilExternalAccount']);

			//create the ilias-user, if a user_id was found
			if ($user_id) {
				$modUserObj = new ilObjUser($user_id);
			}

			$ilLog->write("Syncing user witch ext. acc " . $a_user_data['department'] . "($user_id) with ref_id " . $ref_id->ref_id . "");

			//add user to course
			if ($ref_id->ref_id && $modUserObj) {
				$course = new ilObjCourse($ref_id->ref_id);
				$ilLog->write("Add user " . $modUserObj->getPresentationTitle() . " to course " . $course->getTitle());
				$course->getMemberObject()->add($modUserObj->getId(), IL_CRS_MEMBER);
			}
		}

		return false;
	}


	/**
	 * Returns the course ref_id of the course containing the given meta keyword,
	 * and caches it for the whole ILIAS call (page load).
	 *
	 * @param $meta_keyword
	 *
	 * @return mixed
	 */
	public static function getCrsRefIdByMetaKeyword($meta_keyword) {
		if (!isset(self::$course_ref_id_cache[$meta_keyword])) {
			global $ilLog;

			/**
			 * @var $ilDB  ilDB
			 * @var $ilLog ilLog
			 * @var $set   ilLog
			 */
			global $ilDB;

			$keyword = str_replace(self::LDAP_DEPARTEMENT_PREFIX, self::COURSE_KEYWORD_PREFIX, $meta_keyword);
			$query = 'SELECT ref_id FROM il_meta_keyword ' . 'JOIN object_reference ON object_reference.obj_id = il_meta_keyword.obj_id '
			         . 'WHERE keyword = ' . $ilDB->quote($keyword, 'text');

			$set = $ilDB->query($query);

			$ilLog->write('checking-course with meta-keyword: ' . $meta_keyword . ' and keyword: ' . $keyword . ', found ' . $ilDB->numRows($set)
			              . 'courses');

			self::$course_ref_id_cache[$meta_keyword] = $ilDB->fetchObject($set);
		}

		return self::$course_ref_id_cache[$meta_keyword];
	}


	/**
	 * @return array
	 */
	public function getAdditionalAttributeNames() {
		return array();
	}


	public function assignAttributs() {
	}
}

?>
