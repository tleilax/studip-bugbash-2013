<?php
/**
 * InstituteMember
 * model class for table user_inst
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Andr� Noack <noack@data-quest.de>
 * @copyright   2012 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
*/
class InstituteMember extends SimpleORMap
{

    public static function findByInstitute($institute_id)
    {
        return self::findByInstitut_id($institute_id, 'ORDER BY priority');
    }

    public static function findByUser($user_id)
    {
        return self::findByUser_id($user_id);
    }

    function __construct($id = array())
    {
        $this->db_table = 'user_inst';
        $this->belongs_to = array('user' => array('class_name' => 'User',
                                                    'foreign_key' => 'user_id'),
                                   'institute' => array('class_name' => 'Institute',
                                                    'foreign_key' => 'institut_id')
        );
        $user_getter = function ($record, $field) { return $record->getRelationValue('user', $field);};
        $this->additional_fields['vorname'] = array('get' => $user_getter);
        $this->additional_fields['nachname'] = array('get' => $user_getter);
        $this->additional_fields['username'] = array('get' => $user_getter);
        $this->additional_fields['email'] = array('get' => $user_getter);
        $this->additional_fields['title_front'] = array('get' => $user_getter);
        $this->additional_fields['title_rear'] = array('get' => $user_getter);
        $inst_getter = function ($record, $field) {
            if (strpos($field, 'institute_') !== false) {
                $field = substr($field,10);
            }
            return $record->getRelationValue('institute', $field);
        };
        $this->additional_fields['institute_name'] = array('get' => $inst_getter);
        parent::__construct($id);
    }
}