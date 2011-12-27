<?php
/*
 * Copyright (c) 2011 mlunzena@uos.de, aklassen@uos.de
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

namespace Studip;

/**
 * Represents an abstract interactable element.
 */
abstract class Interactable
{

    public $label, $attributes;

    /**
     * Constructs a new element to interact e.g. button or link
     *
     * @param string $label      the label of the button
     * @param array  $attributes the attributes of the button element
     */
    function __construct($label, $attributes)
    {
        $this->label      = $label;
        $this->attributes = $attributes;
    }

    /**
     * Magic method (triggered when invoking inaccessible methods in a static
     * context) used to dynamically create an interactable element with an
     * additional CSSclass. This works for every static method call matching:
     * /^get(.+)/ The matched group is used as CSS class for the interactable
     * element.
     *
     * @code
     * echo Button::getSubmit();
     *
     * # => <button ... class="submit">...
     * @endcode
     *
     * @param string $name  name of the method being called
     * @param array  $args  enumerated array containing the parameters
     *                      passed to the $name'ed method
     *
     * @return Interactable returns a Button, if $name =~ /^get/
     * @throws              throws a BadMethodCallException if $name does not
     *                      match
     */
    public static function __callStatic($name, $args)
    {
        # only trigger, if $name =~ /^get/ and at least using $label
        if (substr($name, 0, 3) === 'get') {

            # instantiate button from arguments
            $interactable = call_user_func_array(array(get_called_class(), 'get'), $args);
            # but customize with class from $name:
            $class = self::hyphenate(substr($name, 3));

            # a.) set name unless set
            if (!is_string(@$args[1])) {
                $interactable->attributes['name'] =  $class;
            }

            # b.) set/append CSS class
            if (array_key_exists('class', $button->attributes)) {
                $interactable->attributes['class'] .= " $class";
            } else {
                $interactable->attributes['class'] =  $class;
            }

            return $interactable;
        }

        # otherwise bail out
        throw new BadMethodCallException();
    }

    /**
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     *
     * @return returns a Interactable element
     */
    static function get($label = NULL, $trait = NULL, $attributes = array())
    {
        $argc = func_num_args();

        // if label is empty, use default
        $label = $label ?: _('ok');

        // if there are two parameters, there are two cases:
        //   1.) label and trait OR
        //   2.) label and attributes
        //
        // in the latter case, use parameter $trait as attributes
        // and use the default for name
        if ($argc === 2 && is_array($trait)) {
            list($attributes, $trait) = array($trait, NULL);
        }

        $interactable = new static($label, $attributes);

        $interactable->initialize($label, $trait, $attributes);

        return $interactable;
    }

    /**
     * Initialize an interactable element
     *
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     */
    abstract function initialize($label, $trait, $attributes);

    /**
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     */
    static function getAccept($label = NULL, $trait = NULL, $attributes = array())
    {
        $args = func_num_args() ? func_get_args() : array('�bernehmen');
        return self::__callStatic(__FUNCTION__, $args);
    }
    /**
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     */
    static function getCancel($label = NULL, $trait = NULL, $attributes = array())
    {
        $args = func_num_args() ? func_get_args() : array('abbrechen');
        return self::__callStatic(__FUNCTION__, $args);
    }
    
    /**
     * Hyphenates the passed word.
     *
     * @param string $word  word to be hyphenated
     *
     * @return string   hyphenated word
     */
    private static function hyphenate($word)
    {
        return strtolower(preg_replace('/(?<=\w)([A-Z])/', '-\\1', $word));
    }
}