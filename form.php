<?php

/*
 * Copyright 2009-2012 Aptivate Ltd. and Chris Wilson. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE FREEBSD PROJECT ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE FREEBSD PROJECT OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation
 * are those of the authors and should not be interpreted as representing
 * official policies, either expressed or implied, of Aptivate Ltd.
 *
 * <http://www.freebsd.org/copyright/freebsd-license.html>
 *
 * Inspired by "Reece PHP Calendar" by Reece Pegues, but contains no code
 * from that project (any more).
 */

/**
 * Every form has a Context Object, which form values will be taken
 * from if not set in the request, and a set of parameters that come
 * from the request.
 *
 * Forms generate controls (HTML INPUT elements, etc.) with their
 * values preset to properly-escaped versions of the values POSTed
 * with the previous form submission, or if none, then the values
 * from the context object. This is to allow keeping state on form
 * submissions.
 */

require_once(dirname(__FILE__).'/request.php');

class Aptivate_Form
{
	private $formName;
	private $contextObject;
	private $request;
	
	public function __construct($formName, $contextObject,
		$request = null)
	{
		$this->formName = $formName;
		$this->contextObject = $contextObject;
		$this->request = isset($request) ? $request
			: new Aptivate_Request();
	}
	
	public function getContextObject()
	{
		return $this->contextObject;
	}
	
	public function errorsOn($fieldName)
	{
		if (isset($this->contextObject->errors))
		{
			$error_object = $this->contextObject->errors;
			
			if ($error_object instanceof ActiveRecord\Errors)
			{
				return $error_object->on($fieldName);
			}
			elseif (is_array($this->contextObject->errors))
			{
				if (isset($this->contextObject->errors['on']) and
					isset($this->contextObject->errors['on'][$fieldName]))
				{
					return $this->contextObject->errors['on'][$fieldName];
				}
			}
			else
			{
				throw new Exception("Error object is neither ".
					"ActiveRecord\\Errors or an array");
			}
		}
		
		return array();
	}
	
	/**
	 * Convert an array() of attribute values to an HTML attribute
	 * string, with escaping.
	 */
	public function attributes(array $attribs)
	{
		$output = "";
	
		foreach ($attribs as $name => $value)
		{
			if (is_array($value))
			{
				throw new Exception("Attribute $name value should be ".
					"a string, not an array: ".print_r($value, TRUE));
			}
			
			$output .= "$name='".htmlentities($value, ENT_QUOTES)."' ";
		}

		return $output;
	}
	
	/**
	 * @return all values associated with this form, as an array that
	 * can be used to update_attributes() on the contextObject.
	 */
	public function values()
	{
		return $this->request[$this->formName]->params();
	}
	
	public function parameterName($fieldName)
	{
		return $this->formName."[$fieldName]";
	}

	public function currentValue($fieldName)
	{
		$paramName = $this->parameterName($fieldName);
		$values = $this->values();
		
		if (isset($values[$fieldName]))
		{
			return $values[$fieldName];
		}
		
		return $this->contextObject->$fieldName;
	}
	
	function formatFieldWithErrors($fieldHtml, $errors)
	{
		if (count($errors))
		{
			$html = "<span class='field_with_errors'>$fieldHtml";
			
			if (!is_array($errors))
			{
				// ActiveRecord\Errors returns a simple string
				// if there's only one error
				$html .= "<span class='error'>$errors</span>\n";
			}
			elseif (count($errors) > 1)
			{
				$html .= "<ul class='error'>\n";
				foreach ($errors as $error)
				{
					$html .= "<li>$error</li>\n";
				}
				$html .= "</ul>\n";
			}
			else
			{
				$html .= "<span class='error'>$errors[0]</span>\n";
			}
			
			$html .= "</span>\n";
			return $html;
		}
		else
		{
			return $fieldHtml;
		}
	}

	function textBox($fieldName, $attribs = array(), $read_only = FALSE)
	{
		if ($read_only)
		{
			return htmlentities($this->currentValue($fieldName));
		}
		else
		{
			$attribs = array_merge(
				array(
					'type' => 'text',
					'name' => $this->parameterName($fieldName),
					'value' => $this->currentValue($fieldName),
					),
				$attribs
				);
			
			$html = '<input '.$this->attributes($attribs).'/>';	
			$errors = $this->errorsOn($fieldName);
			return $this->formatFieldWithErrors($html, $errors);
		}
	}

	function textBoxRow($label, $fieldName, $read_only = FALSE,
		$attribs = array(), $help_text = null)
	{
		
		$output = "
		<tr>
			<th>$label";
	
		if (!$read_only AND $help_text)
		{
			$output .= $this->helpTextLink();
		}
	
		$output .= "
			</th>
			<td>";
	
		if ($read_only)
		{
			$value = $this->currentValue($fieldName);
			$output .= htmlentities($value);
		}
		else
		{
			$output .= $this->textBox($fieldName, $attribs);
		
			if ($help_text)
			{
				$output .= $this->helpTextSpan($help_text);
			}
		}
	
		$output .= "
			</td>
		</tr>";
	
		return $output;
	}

	function textArea($fieldName, $attribs = array())
	{
		$attribs = array_merge(
			array('name' => $this->parameterName($fieldName)),
			$attribs
			);
		$value = htmlentities($this->currentValue($fieldName),
			ENT_QUOTES);
		$html = "<textarea ".$this->attributes($attribs)."/>".
			$value."</textarea>";
		$errors = $this->errorsOn($fieldName);
		return $this->formatFieldWithErrors($html, $errors);
	}
	
	function submitButton($label, $name = "commit")
	{
		$label = htmlentities($label, ENT_QUOTES);
		return "<input type='submit' name='$name' value='$label' />";
	}

	function helpTextSpan($help_text)
	{
		return "<span class='hint'>".htmlentities($help_text)."</span>";
	}

	function helpTextLink()
	{
		return "<a href='#' onclick='displayHint(this); return false'>?</a>";
	}

	function selectKey($fieldName, $options, $attribs = array())
	{
		$selected_value = $this->currentValue($fieldName);
		$output = "
			<select name='".$this->formName."[$fieldName]' ".
			$this->attributes($attribs).">";
		
		foreach ($options as $value => $label)
		{	
			$output .= "
				<option value='".htmlentities($value, ENT_QUOTES)."'";
			if ($selected_value == $value)
			{
				$output .= ' selected="selected"';
			}
			$output .= ">".htmlentities($label)."</option>";
		}
	
		$output .= "
			</select>";
	
		return $output;
	}

	function selectObject($fieldName, $modelObjects, $labelField,
		$attribs = array())
	{
		$options = array();
		
		foreach ($modelObjects as $o)
		{
			$options[$o->id] = $o->$labelField;
		}
		
		return $this->selectKey($fieldName, $options, $attribs);
	}

	function selectValue($fieldName, $options, $attribs = array())
	{	
		$selected_value = $this->currentValue($fieldName);
		$output = "
			<select name='".$this->formName."[$fieldName]' ".
			$this->attributes($attribs).">";
		
		foreach ($options as $index => $label)
		{	
			$output .= "
				<option ";
			if ($selected_value == $label)
			{
				$output .= ' selected="selected"';
			}
			$output .= ">".htmlentities($label)."</option>";
		}
	
		$output .= "
			</select>";
	
		return $output;
	}
	
	function tableRow($label, $html)
	{
		$output = "
		<tr>
			<th>$label</th>
			<td>$html</td>
		</tr>";
	
		return $output;
	}

	/**
	 * Override __get to overload -> operator, to support using the
	 * Form object as a proxy for the context object wrapped inside.
	 */
	public function __get($name)
	{
		return $this->contextObject->$name;
	}

	function hidden($fieldName)
	{
		return $this->hiddenAny($this->parameterName($fieldName),
			$this->currentValue($fieldName));
	}

	function hiddenAny($fieldName, $value)
	{
		$attribs = array(
			'type' => 'hidden',
			'name' => $fieldName,
			'value' => $value,
			);
		return '<input '.$this->attributes($attribs).'/>';
	}

	function booleanControlWithLabel($fieldName, $controlValue, $label,
		$multipleSelect = FALSE, $attribs = array())
	{
		if (!isset($attribs['id']))
		{
			$attribs['id'] = $this->formName."_".$fieldName;
		}
		
		$fieldValue = $this->currentValue($fieldName);
		
		if ($multipleSelect)
		{
			if (in_array($controlValue, $fieldValue))
			{
				$attribs["checked"] = "checked";
			}
		}
		elseif (isset($fieldValue) and $controlValue == $fieldValue)
		{
			$attribs["checked"] = "checked";
		}
		
		$nameAttribute = $this->parameterName($fieldName);
		
		if ($multipleSelect)
		{
			$nameAttribute .= "[]";
		}
		
		$attribs = array_merge(
			array(
				'type' => 'radio',
				'id' => $this->formName."_".$fieldName,
				'name' => $nameAttribute,
				'value' => $controlValue),
			$attribs);
			
		return "<input ".$this->attributes($attribs)."/>\n".
			"<label for='".$attribs['id']."'>".htmlentities($label).
			"</label>\n";
	}
	
	function radioButtonWithLabel($fieldName, $radioValue, $label,
		$attribs = array())
	{
		return $this->booleanControlWithLabel($fieldName, $radioValue,
			$label, FALSE,
			array_merge(array('type' => 'radio'), $attribs));
	}

	function radioButtonSet($fieldName, $options, $legend = "",
		$css_class = "", $as_list = FALSE)
	{
		$output = "";
	
		if ($as_list)
		{
			$output .= "
			<ul>
				<li class='list_title'>$legend</li>";
		}
		else if ($legend)
		{
			$output .= "
			<legend>$legend</legend>";
		}
	
		foreach ($options as $i => $thisValue)
		{
			if ($as_list)
			{
				if ($css_class)
				{
					$output .= "<li class='".$css_class."_".
						preg_replace('|[^A-Za-z0-9]+|', '_', $value).
						"'>\n";
				}
				else
				{	
					$output .= "<li>\n";
				}
			}
		
			$output .= $this->radioButtonWithLabel($fieldName,
				$i /* submitted value */, $thisValue /* form label */);

			if ($as_list)
			{	
				$output .= "</li>";
			}
		}
	
		if ($as_list)
		{
			$output .= "
				</ul>";
		}
		
		$errors = $this->errorsOn($fieldName);
		$output = $this->formatFieldWithErrors($output, $errors);
		$output = "
			<fieldset class='$css_class'>
			$output
			</fieldset>";
		
		return $output;
	}

	function checkBoxWithLabel($fieldName, $checkboxValue, $label,
		$attribs = array())
	{
		return $this->booleanControlWithLabel($fieldName, $checkboxValue,
			$label, TRUE,
			array_merge(array('type' => 'checkbox'), $attribs));
	}

	/**
	 * Returns the HTML for a fieldset of checkboxes, where the values
	 * of the checkboxes equal their labels, instead of their indexes
	 * into the $options array.
	 */
	function checkBoxSetIdentity($fieldName, $options, $legend = "",
		$css_class = "", $as_list = FALSE)
	{
		$identity_values = array();
		foreach ($options as $i => $value)
		{
			$identity_values[$value] = $value;
		}
		return $this->checkBoxSet($fieldName, $identity_values,
			$legend, $css_class, $as_list);
	}
	
	/**
	 * Returns the HTML for a fieldset of checkboxes, where the values
	 * of the checkboxes equal their indexes into the $options array,
	 * and the labels are the values of the $options array.
	 */
	function checkBoxSet($fieldName, $options, $legend = "",
		$css_class = "", $as_list = FALSE)
	{
		$output = "";

		if ($legend)
		{
			$output .= "
			<legend>$legend</legend>";
		}
		
		if ($as_list)
		{
			$output .= "
			<ul>";
		}

		foreach ($options as $i => $value)
		{
			if ($as_list)
			{
				if ($css_class)
				{
					$output .= "<li class='".$css_class."_".$i."'>\n";
				}
				else
				{	
					$output .="<li>\n";
				}
			}
			$output .= $this->checkBoxWithLabel($fieldName,
				$i, $value);
			if ($as_list)
			{
				$output .= "</li>";
			}
		}
	
		if ($as_list)
		{
			$output .= "
			</ul>";
		}

		$errors = $this->errorsOn($fieldName);
		$output = $this->formatFieldWithErrors($output, $errors);
		$output = "
			<fieldset class='$css_class'>
			$output
			</fieldset>";
	
		return $output;
	}
};

/**
 * Same kind of form builder as Aptivate_Form, but works over an
 * array of model objects, and each call takes an index into that
 * array as an extra parameter.
 */
class Aptivate_Table
{
	private $formName;
	private $contextObjects;
	private $request;
	
	public function __construct($formName, $contextObjects = array(),
		$request = null)
	{
		$this->formName = $formName;
		$this->contextObjects = $contextObjects;
		$this->request = isset($request) ? $request
			: new Aptivate_Request();
	}
	
	/**
	 * Convert an array() of attribute values to an HTML attribute
	 * string, with escaping.
	 */
	public function attributes($attribs)
	{
		$output = "";
	
		foreach ($attribs as $aname => $avalue)
		{
			$output .= "$aname='" . htmlentities($avalue, ENT_QUOTES) . "' ";
		}

		return $output;
	}
	
	function subForm($index)
	{
		if (!isset($this->contextObjects[$index]))
		{
			throw new Exception("No such object $index in ".
				$this->contextObjects);
		}
		
		return new Aptivate_Form($this->formName."[$index]",
			$this->contextObjects[$index],
			$this->request[$this->formName][$index]);
	}

	function textBox($index, $fieldName, $attribs = array())
	{
		return $this->subForm($index)->textBox($fieldName, $attribs);
	}

	function textBoxRow($index, $label, $fieldName, $read_only = FALSE,
		$attribs = array(), $help_text = null)
	{
		return $this->subForm($index)->textBoxRow($label, $fieldName,
			$read_only, $attribs, $help_text);
	}
	
	function selectKey($index, $fieldName, $options, $attribs = array())
	{
		return $this->subForm($index)->selectKey($fieldName, $options,
			$attribs);
	}

	function selectObject($fieldName, $modelObjects, $labelField,
		$attribs = array())
	{
		return $this->subForm($index)->selectKey($fieldName,
			$modelObjects, $labelField, $attribs);
	}
	
	function tableRow($index, $label, $html)
	{
		return $this->subForm($index)->tableRow($label, $html);
	}
};
 
 
/*
function textArea($name, $value = "", $attribs = array())
{
	$value = htmlentities($name, ENT_QUOTES);
	$output = "<textarea name='$name' " . attributes($attribs) . "/>" . 
			$value . "</textarea>";
	
	return $output;
}
*/

function textAreaWithOverride($name, $value = "", $attribs = array())
{
	$value = overrideParam($name, $value);
	return textArea($name, $value, $attribs);
}

function textBoxWithOverride($name, $value = "", $attribs = array())
{
	$value = overrideParam($name, $value);
	return textBox($name, $value, $attribs);
}


function html_row($label, $value)
{
	$output = "
	<tr>
		<th>$label</th>
		<td>$value</td>
	</tr>";
	
	return $output;
}

function text_row($label, $value)
{
	return html_row($label, htmlentities($value));
}

/*
function textAreaRow($label, $name, $record, $read_only, $attribs = array(),
	 $help_text = null)
{
	$value = $record['name'];
	$table_data = textAreaWithOverride($name, $value, $attribs);
	$output = html_row($label, $table_data);
	
	return $output;
}
*/



/*
 * Format text to HTML paragraphs and breaks.
 */
function formatText($text)
{
	// replace multiple BR with P
	$html_text = nl2br($text);
	return "<p>" . $html_text . "</p>";
}

function textAreaRow($label, $name, $record, $read_only, $attribs = array(),
	$css_id = "", $rich_editor = False)
{
	$value = $record[$name];
	$output = "
	<tr>
		<th>$label</th>
		<td id='$css_id'>";
	if ($read_only)
	{
		if (preg_match("#<#", $value))
		{
			 $output .= $value;
		}
		else
		{
			$text = htmlentities($value);
			if ($text != "") 
			{
				$output .= formatText($text);
			}
		}
	}
	else if ($rich_editor)
	{
		$output .= richTextArea($name, $value, $attribs);
	}
	else
	{
		$output .= textArea($name, $value, $attribs);
	}
	$output .= "
		</td>
	</tr>";
	return $output;
}

/* 
	Create a set of hidden fields with the name "$field_name[]" for values in
	the list $options.
*/
function hidden_field_set($field_name, $options)
{
	$output = "";		

	foreach($options as $id => $junk)
	{	
		$output .= hidden_field($field_name."[]", $id);
	}

	return $output;
}


// returns HTML for a month select box
function selectMonth($name, $value, $attribs = array())
{
	$value = overrideParam($name, $value);
	
	$output = "
		<select name='$name' ".attributes($attribs).">";
	
	$months = array("January", "February", "March", "April",
		"May", "June", "July", "August",
		"September", "October", "November", "December");
	
	foreach ($months as $i => $month)
	{
		$output .= "
			<option value='".($i+1)."' " .
			($value == ($i+1) ? "selected='selected' " : "") .
			">$month</option>";
	}
	
	$output .= "
		</select>";
	
	return $output;
}

// returns HTML for a select box for hours of the day (12/24 hr clock)
function selectHour($name, $value, $attribs = array())
{
	$value = overrideParam($name, $value);
	
	$output = "
		<select name='$name' ".attributes($attribs).">";

	// print out the hour drop down
	if (!cal_option("hours_24"))
	{
		for ($i = 1; $i <= 12; $i++)
		{
			$output .= '
				<option value="' . $i % 12 . '"';
			if ($value == $i) $output .= ' selected="selected"';
			$output .= ">$i</option>";
		}
	}
	else
	{
		for ($i = 0; $i < 24; $i++)
		{
			$output .= "
				<option value=\"$i\"";
			if ($value == $i) $output .= ' selected="selected"';
			$output .= ">$i</option>";
		}
	}
	
	$output .= "
		</select>";

	return $output;
}

// returns HTML for a select box for minutes past the hour
function selectMinute($name, $value, $attribs = array())
{
	$value = overrideParam($name, $value);
	
	$output = "
		<select name='$name' ".attributes($attribs).">";
					
	for ($i = 0; $i < 60; $i = $i + 15)
	{
		$output .= "
			<option value='$i'";
		if ($value >= $i && $value < $i + 15)
		{
			$output .= ' selected="selected"';
		}
		$output .= sprintf(">%02d</option>", $i);
	}
	
	$output .= "
		</select>";
	
	return $output;
}

// returns HTML for an AM/PM select box, if using 12-hour clock
function selectAmPm($name, $value, $attribs = array())
{
	$value = overrideParam($name, $value);
	
	if (cal_option("hours_24"))
	{
		return "";
	}
	
	$output = "
		<select name='$name' ".attributes($attribs).">
			<option value='0' ".(empty($value) ? 'selected="selected"' : "").">AM</option>
			<option value='1' ".($value ? 'selected="selected"' : "").">PM</option>
		</select>";
	
	return $output;
}

function selectLabelFromList($name, $value, $options, $attribs = array())
{
//	$value = overrideParam($name, $value);
	
	$output = "
		<select name='$name' ".attributes($attribs).">";
					
	foreach ($options as $index => $label)
	{
		$output .= "
			<option value='".htmlentities($label, ENT_QUOTES)."'";
		if ($value == $label)
		{
			$output .= ' selected="selected"';
		}
		$output .= ">".htmlentities($label)."</option>";
	}
	
	$output .= "
		</select>";
	
	return $output;
}

function selectLabelFromListWithOverride($name, $value, $options, $attribs = array())
{
	$value = overrideParam($name, $value);
	return  selectLabelFromList($name, $value, $options, $attribs);
}

function checkBox($name, $checked, $checked_value, $attribs = array())
{
	$value = overrideParam($name, $value);

	$output = "<input type='checkbox' name='$name' value='$checked_value' " .
		($checked ? "checked='checked' " : "") .
		attributes($attribs) . "/>";

	return $output;
}

function richTextArea($name, $value, $attribs = array())
{
	$value = overrideParam($name, $value);

	$output = "<textarea name='$name' ".attributes($attribs).">" .
		$value ."</textarea>";

	return $output;
}

function textArea($name, $value, $attribs = array())
{
	$value = overrideParam($name, $value);

	$output = "<textarea name='$name' ".attributes($attribs).">" .
		htmlentities($value, ENT_QUOTES)."</textarea>";

	return $output;
}

function workstreams()
{
	return array("PFM", "PSM", "PS", "M&E", "KM", "Fed", "General");
}

function calendars()
{
	return array("Abuja", "Enugu", "Jigawa", "Kaduna", "Kano",
		"Lagos", "Other", "Leave", "SLP");
}

function documentTypes()
{
	return array("DFID Reports", "Government Documents", "SPARC Final Reports",
		"SPARC Briefs", "SPARC Interim Reports", "SPARC Visit Reports",
		"SPARC ToR", "Minutes of Meetings", "Admin Documents", "Other");
}

function teams()
{
	return array("MIT", "PDG", "STL", "SnrM");
}

function states()
{
	return array("Abuja", "Enugu", "Jigawa", "Kaduna", "Kano",
		"Lagos", "Other");
}


function option_element($text, $selected, $selected_value, $attribs = array())
{
	$value = overrideParam($name, $value);
	
	$output = "<option value='".htmlentities($selected_value)."' ".
				($selected ? "selected='selected'" : "")." ".
				attributes($attribs).">".
				htmlentities($text)."</option>\n";
	
	return $output;
}

function option_element_exclusive($text, $selected, $selected_value,
	$inviting_list, $attribs = array())
{
	if (!($inviting_list XOR $selected))
	{
		return option_element($text, FALSE, $selected_value, $attribs);
	}
}

function listOfUsers($name, array $all_users, array $inviting_groups,
	array $inviting_users, array $inviting_emails,
	$email_supported, $inviting_list, $css_style = "")
{
	global $cal_db;
	
	$output = "
	<select name='$name' multiple='multiple' size='8'" .
		($css_style ? " style='$css_style'" : "") .
		">".option_element_exclusive("All users", $all_users, "all",
			$inviting_list)."
		<optgroup label='Groups'>";	
		
	foreach (cal_list_groups() as $i => $group)
	{
		$group_selected = array_key_exists($group['id'], $inviting_groups);
		$output .= option_element_exclusive($group['group_name'],
			$group_selected, "group_".$group['id'], $inviting_list);
	}
	$output .= "
		</optgroup>";
	
	$output .= "
		<optgroup label='Users'>\n";		
	$users = cal_query_getallusers();
	while ($user = $cal_db->sql_fetchrow($users))
	{
		$fullname = $user['first_name']." ".$user['last_name'];
		$user_selected = array_key_exists($user['id'], $inviting_users);
		$output .= option_element_exclusive($fullname, $user_selected,
			"user_".$user['id'], $inviting_list);
	}
	$output .= "
		</optgroup>";
		
	if ($email_supported AND $inviting_list)
	{
		$output .= "
		<optgroup label='Email' value=''>\n";
		
		foreach ($inviting_emails as $email => $junk)
		{
			$output .= option_element($email, FALSE, "email_".$email);
		}
		
		$output .= "
		</optgroup>";
	}
	
	$output .= "
	</select>";
	return $output;
}

function attachFiles($field, $value, $delete_action, $read_only,
	$published = false)
{
	global $cal_db;
	
	$output ="
			<tr>
				<th>&nbsp;</th>
				<td>
					<ul>";
	if($published)
	{
		$attachments = cal_query_get_attachments($field, $value, "published_files");
	}
	else
	{
		$attachments = cal_query_get_attachments($field, $value);
	}
	
	while ($attachment = $cal_db->sql_fetchrow($attachments))
	{
		$output .= "
						<li>";
		
		if ($published)
		{
			$download_url="download.php?aid=" . $attachment['id'] .
				"&amp;published"; 
		}
		else
		{
			$download_url="download.php?aid=" . $attachment['id'] .
				(isset($attachment['document_id']) ? "&amp;did=" .
					$attachment['document_id'] : "");
		}
		
		$output .= "<a href='" . $download_url . "'>" . $attachment['name'] .
					"</a>" . 
					" Size: " . (int)($attachment['size'] / 1024) . " KB ";
		
		if (!$read_only)
		{
			$output .= "
							<a href='index.php?action=$delete_action&amp;aid=".
								$attachment['id'] . "'
								onClick='return confirm(\"Are you sure?\");'
								id='attachment_" . $attachment['id'] . "'>
							Delete attachment</a>";
		}
		
		$output .= "
						</li>";
	}

	$output .= "
					</ul>
				</td>
			</tr>";
	if (!$read_only)
	{		
		$output .="
			<tr>
				<th>Attach file</th>
				<td>
					<input type='file' name='new_attachment_data' />
					<input type='submit' name='new_attachment_add'
						value='Upload' />
				</td>
			</tr>";
	}
	return $output;
}

function checkBoxWithoutOverride($tid, $name, $field_value, $checkbox_value,
	$label, $attribs = array())
{
	$name_h  = htmlentities($name);
	$value_h = htmlentities($checkbox_value);
	$label_h = htmlentities($label);
	
	$values = explode(",", $field_value);

	if (in_array((String)$checkbox_value, $values))
	{
		$attribs["checked"] = "checked";
	}

	$output = 	"<input id='$tid' type='checkbox' value='$value_h' " .
				"name='$name_h" . "[]' " . attributes($attribs) . " />\n";
	$output .= 	"<label for='$tid'>$label_h</label>\n";	
	return $output;
}

function deselectableCheckBoxSet($field_name, $options)
{
	$output = "";		
	$checked = implode(",", array_keys($options));
	$id_counter = 0;

	foreach($options as $id => $name)
	{
		if (is_numeric($id))
		{
			$field_id = $field_name."_".$id;
		}
		else
		{
			$field_id = $field_name."_".($id_counter++);
		}
		
		$output .= checkBoxWithoutOverride($field_id, $field_name, $checked,
			$id, $name);
	}

	return $output;
}

function tableRow($label, $fieldset_class, $html, $help_text = null)
{
	$th_html = $label;
	$td_html = "<fieldset class='$fieldset_class'>$html</fieldset>";
	
	if ($help_text)
	{
		$th_html .= helpTextLink();
		$td_html .= helpTextSpan($help_text);
	}
	
	return html_row($th_html, $td_html);
}

function checkBoxRow($label, $name, $record, $options, $read_only,
	$css_class = "", $help_text = null)
{
	$value = $record[$name];
	
	$output = "
	<tr>
		<th>$label";
	
	if (!$read_only AND $help_text)
	{
		$output .= helpTextLink();
	}
	
	$output .= "
		</th>
		<td>";
	
	if ($read_only)
	{
		$output .= htmlentities($value);
	}
	else
	{
		$output .= checkBoxSet($name, $value, $options, "", $css_class);
		
		if ($help_text)
		{
			$output .= helpTextSpan($help_text);
		}
	}
	
	$output .= "
		</td>
	</tr>";
	return $output;
}

function checkBoxDiv($label, $name, $record, $options, $read_only, $div_class,
	$div_id, $fieldset_class = "", $help_text = null)
{
	$value = $record[$name];
	
	if ($read_only)
	{
		$output .= htmlentities($value);
	}
	else
	{
		if ($help_text)
		{
			$label_html = $label . helpTextLink();
		}
		else
		{
			$label_html = $label;
		}
		
		$output .= "<div class='$div_class' id='$div_id'>".
		checkBoxSet($name, $value, $options, $label_html, $fieldset_class, TRUE);
		
		if ($help_text)
		{
			$output .= helpTextSpan($help_text);		
		} 
		
		$output .= "
		</div>";
	}

	return $output;
}

function find_in_set($param_name, &$query_array, $sql_field_name = "")
{
	if (!$sql_field_name)
	{
		$sql_field_name = $param_name;
	}
	
	if ($_POST[$param_name])
	{
		$values = $_POST[$param_name];
	}
	else if ($_GET[$param_name])
	{
		$values = $_GET[$param_name];
	}
	
	if ($values)
	{
		$sql_condition = "";
		foreach ($values as $i => $value)
		{
			if ($sql_condition != "")
			{
				$sql_condition .= " OR ";
			}
			$sql_condition .= "FIND_IN_SET('".addslashes($value).
				"', $sql_field_name)";
		}
		$query_array[] = "(" . $sql_condition . ")";
	}
}

function radioButtonDiv($label, $name, $record, $options, $read_only, $div_class,
	$div_id, $li_class = "", $help_text = null)
{
	$value = $record[$name];

	if ($read_only)
	{
		$output .= htmlentities($value);
	}
	else
	{
		if ($help_text)
		{
			$label_html = $label . helpTextLink();
		}
		else
		{
			$label_html = $label;
		}
		
		$output = "
		<div class='$div_class' id='$div_id'>".
		radioButtonSet($name, $value, $options, $label_html, $li_class, TRUE);
				
		if ($help_text)
		{
			$output .= helpTextSpan($help_text);
		} 	
		
		$output .= "
		</div>";
	
	}
	
	return $output;
}

function radioButtonRow($label, $name, $record, $options, $read_only,
	$css_class = "", $help_text = null)
{
	$value = $record[$name];
	
	$output = "
	<tr>
		<th>$label";
	
	if (!$read_only AND $help_text)
	{
		$output .= helpTextLink();
	}
	
	$output .= "
		</th>
		<td>";
	
	if ($read_only)
	{
		$output .= htmlentities($value);
	}
	else
	{
		$output .= radioButtonSet($name, $value, $options, "", $css_class);
		
		if ($help_text)
		{
			$output .= helpTextSpan($help_text);
		}
	}
	
	$output .= "
		</td>
	</tr>";
	return $output;
}

function error_message($error)
{
	return "<div class='error_message'>".htmlentities($error, ENT_QUOTES)."</div>";
}

function notice_error($error)
{
	return array("notice", error_message($error));
}

function notice_success($error)
{
	return array("notice", success_message($error));
}

function success_message($success)
{
	return "<div class='success_message'>".htmlentities($success, ENT_QUOTES)."</div>";
}

function warning_message($warning)
{
	return "<div class='warning_message'>".htmlentities($warning, ENT_QUOTES)."</div>";
}

?>
