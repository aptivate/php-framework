<?php
require_once(dirname(__FILE__).'/../request.php');
require_once(dirname(__FILE__).'/../form.php');

class AptivateFormTest extends PHPUnit_Framework_TestCase
{
	/**
	 * Aptivate_Form should generate submit buttons with names prefixed by
	 * (indexed into) the form name, so that their values are present
	 * properly in the form's subset of the request, and don't accidentally
	 * override the whole form's data if they have the same name as the form.
	 */
	public function test_form_submit_button_names_are_prefixed_with_form_name()
	{
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$form = new Aptivate_Form('search', (object)array(), $req);
		$this->assertEquals("<input type='submit' name='search[commit]' ".
			"value='Do Something!' />", $form->submitButton("Do Something!"),
			"The submit button's name is not prefixed by the form name, ".
			"or something else is wrong with the generated HTML.");
	}
}
?>
