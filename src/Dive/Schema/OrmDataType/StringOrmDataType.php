<?php
/*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Dive\Schema\OrmDataType;

/**
 * Class StringOrmDataType
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 * @created 04.07.2014
 */
class StringOrmDataType extends OrmDataType
{

    /**
     * Validates whether the value matches the field type, or not
     *
     * @param  mixed $value
     * @param  array $field
     * @return bool
     */
    public function validateType($value, array $field)
    {
        return is_string($value);
    }


    /**
     * Validates whether the value fits to the field length, or not
     *
     * @param  mixed $value
     * @param  array $field
     * @return bool
     */
    public function validateLength($value, array $field)
    {
        if (isset($field['length'])) {
            $length = $field['length'];
            $charset = isset($field['charset']) ? $field['charset'] : 'UTF-8';
            $stringLength = mb_strlen($value, $charset);
            return ($stringLength !== false && $stringLength <= $length);
        }
        return true;
    }
}
