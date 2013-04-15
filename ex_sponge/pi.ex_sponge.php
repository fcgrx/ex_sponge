<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2013 FCGRX.
*/

$plugin_info = array(
						'pi_name'			=> 'ExSponge',
						'pi_version'		=> '0.8.4',
						'pi_author'			=> 'Dan Prothero',
						'pi_author_url'		=> 'http://fcgrx.com/',
						'pi_description'	=> 'Cleans up the garbage that text editors and word processors leave behind',
						'pi_usage'			=> Ex_sponge::usage()
					);

/**
 * Ex_sponge Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Dan Prothero
 * @copyright		Copyright (c) 2013, FCGRX.
 * @link			http://fcgrx.com/
 */

class Ex_sponge {

	var $return_data;

	/**
	* Constructor
	*
	*/
	function Ex_sponge($str = '')
	{
		$this->EE =& get_instance();

		$allow_tags = ( ! $this->EE->TMPL->fetch_param('allow_tags')) ? 'yes' :  $this->EE->TMPL->fetch_param('allow_tags');
		$allow_breaks = ( ! $this->EE->TMPL->fetch_param('allow_breaks')) ? 'no' :  $this->EE->TMPL->fetch_param('allow_breaks');
		$allow_styles = ( ! $this->EE->TMPL->fetch_param('allow_styles')) ? 'no' :  $this->EE->TMPL->fetch_param('allow_styles');
		$convert_tags = ( ! $this->EE->TMPL->fetch_param('convert_tags')) ? 'yes' :  $this->EE->TMPL->fetch_param('convert_tags');
		$paragraphs = ( ! $this->EE->TMPL->fetch_param('paragraphs')) ? '-1' :  $this->EE->TMPL->fetch_param('paragraphs');

		$str = ($str == '') ? $this->EE->TMPL->tagdata : $str;

		// MICROSOFT WORD CLEANUP
// remove non-printing / control characters
$str = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/','',$str);
		// replace special entities
		$search = array('/&amp;QUOT;/u', '/&#47;/u', '/&lsquo;/u', '/&rsquo;/u', '/&ldquo;/u', '/&rdquo;/u', '/&mdash;/u');
		$replace = array('&quot;', '/', '\'', '\'', '"', '"', '-');
		$str = preg_replace($search, $replace, $str);
		// decode all other entities
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		// remove title, head, style, script and form tags (and everything inside them)
		$str = preg_replace('#<(title|head|style|form|script|object|applet)[^>]*>.*</\1>#imU','',$str);
		// remove Word / HTML comments
		$str = preg_replace('/<!--(.*)-->/Uis','',$str);
		// remove C-type comments
		$str = mb_eregi_replace('#/\*.*?\*/#s', '', $str, 'm');
		// remove layout-breaking, proprietary and meaningless tags
		// TO DO: GIVE MORE CONTROL OVER THIS?
		$str = preg_replace('#</?(\!|html|body|head|style|meta|link|iframe|frame|frameset|font|del|ins|o:|v:)[^>]*>#imU','',$str);
		if ($allow_styles != 'yes')
		{
			// remove all parameters from paragraphs and elementary tags
			$str = preg_replace('/<(p|b|i|strong|em|br)\s[^>]*>/imsU', '<$1>', $str);
			// remove styling from all tags
			// TO DO: USE WHITELIST INSTEAD OF BLACKLIST 
			$str = preg_replace('#(<[^>]+\s*)(style|class|lang|size|face|align|font|onmouseover|onclick)=("[^"]+"|\'[^\']+\'|[^\'">]+\w)([^>]*>)#imU', "$1$4", $str);
			// BLOGGER CLEANUP
			// remove target and onclick parameters Blogger inserts into links
			$str = preg_replace('#<a\s+[^>]*(href="[^"]*")[^>]+>#imU', '<a $1>', $str);
		}
		// remove proprietary v: and o: parameters from all tags
		$str = preg_replace('#(<[^>]+)(v|o):[^=>]*="[^"]*"([^>]*>)#imU', "$1$3", $str);
		// fix dash entities
		$str = str_replace('Ã¢â‚¬â€œ','&mdash;',$str);
		// remove anchor links
		$str = preg_replace('#<a name=[^>]*>\s*</a>#imU',' ',$str);
		// convert presentational tags to their semantic equivalent
		if ($convert_tags != 'no')
		{
			$search = array('#<(/?)i\b[^>]*>#ui', '#<(/?)b\b[^>]*>#ui');
			$replace = array('<$1em>','<$1strong>');
			$str = preg_replace($search, $replace, $str);
		}

		// EE RTE CLEANUP
		// Remove phantom &#8203; garbage that may be created by EE's rte
		$str = str_replace('​', '', $str); // NOTE: this is not an empty string!

		// wrap text in <p>, in case it's an unformatted (tag-free) text block
		// (we will clean this up below)
		$str = "<p>$str</p>";

		// prevent the loss of arithmetic expressions ( 2<3 ) before stripping tags
		$str = preg_replace('/<([0-9]+)/', ' < $1', $str);

		// option to strip tags
		if ($allow_tags == 'yes')
		{
			// by default, we remove span, div, alignment, and form tags
			$str = preg_replace('#</?(div|span|center|input|checkbox|select|button|textarea)[^>]*>#imU','',$str);
		}
		else
		{
			$str = str_replace('<',' <',$str); // before stripping tags (incl <p>) insert spaces so word breaks survive
			if ($allow_tags != strip_tags($allow_tags))
				$str = strip_tags($str,$allow_tags);
			else
				$str = strip_tags($str);
		}

		// Odd characters can make empty paragraphs look non-empty.
		// It seems the only way to remove them to target <p>%C2%A0</p>
		$str = urlencode($str);
		$str = str_replace ("%3Cp%3E%C2%A0%3C%2Fp%3E","",$str);
		// TinyMCE can introduce these too
		$str = str_replace ("%C2%A0"," ",$str);
		$str = urldecode($str);

		// compact all whitespace (and convert tabs & carriage returns to spaces)
		$str = trim(preg_replace('/\s\s*/m', ' ',$str));
		// remove whitespace between tags
		$str = preg_replace('/>\s+</m', '><',$str);
		// remove extra whitespace at beginning and end of paragraph, header, list, etc
		$str = preg_replace('#\s*<(p|h[1-6]|ol|ul|li)>\s*#im', '<$1>', $str);
		// remove spaces before end tags
		$str = preg_replace('#\s+<(/[^>]+)\s*>#imU', '<$1>', $str);
		// remove spaces within header tags (i.e. <h1>   HEADLINE  </h2>)
		$str = preg_replace('#(<(h[1-6])>)\s+#im', '$1', $str);

		// normalize BR's
		$str = str_replace("<br>", "<br />", $str);
		// remove BR's in headers
		$str = preg_replace('#(<br />)\s*(</(h[1-6])>)#m', '$2',$str);
		 // remove BR's from lists
		$str = preg_replace('#(<br />)\s*(</(li)>)#m', '$2',$str);
		// optionally change all (remaining) BR's to paragraphs
		if ($allow_breaks == 'no')
		{
			$str = preg_replace('#(<br />\s*)+#m', '</p><p>',$str);
		}

		// CLEAN UP PARAGRAPHS
		// make sure all paragraphs are closed (we clean this up below)
		$str = str_replace('<p>', '</p><p>', $str);
		// Start paragraphs after any header tag finishes (we clean this up below)
		$str = preg_replace('#(</(h[1-6])>)#im', '$1<p>', $str);
		// remove BR's at the beginning of p's
		$str = preg_replace('#<p>\s*(<br />)+#', '<p>', $str);
		// change double opening P's to single opening P's
		$str = preg_replace('#(<p>\s*)+#', '<p>', $str);
		// change double closing P's to single closing P's
		$str = preg_replace('#(</p>\s*)+#', '</p>', $str);
		// don't wrap lists in paragraphs
		$str = preg_replace('#<p>\s*<(ol|ul)[^>]*>#m', '<$1>', $str);
		$str = preg_replace('#</(ol|ul)\s*>\s*</p>#m', '</$1>', $str);
		// remove empty tags and paragraphs
		$str = preg_replace('#<([^</>]*)>([\s]*?|(?R))</\1>#imsU', '', $str);

		// fix tags that close and immediately reopen (i.e. </strong><strong>)
		$str = preg_replace('#</(b|strong|em|i)[^>]*>\s*<\1>#imU',' ',$str);

		// remove orphaned tags at beginning of text (i.e. </p> at beginning)
		$str = preg_replace('#^\s*</[^>]+>#', '', $str);
		// remove orphaned tags (other than <img />) at end of text (i.e. <p> at end)
		$str = preg_replace('#<[^/>]*[^/]>\s*$#mU', '', $str);

		// Prettify: Add a carriage return after some closing tags
		$str = preg_replace('#(</(p|h[1-6]|ol|ul|li)>)#i', "$1\n",$str);

		if ($paragraphs > 0)
		{
			$chunks = explode('<p>', $str);
			$chunks = array_slice($chunks,0,$paragraphs);
			$str = implode('<p>', $chunks);
		}

 		$this->return_data = trim($str);
	}

	// --------------------------------------------------------------------

	/**
	* Usage
	*
	* Plugin Usage
	*
	* @access	public
	* @return	string
	*/
	function usage()
	{
		ob_start();
		?>

This plugin cleans up your mess!

Whether your content was entered via WYSIWYG (Rich Text) editors (such as TinyMCE, CKEditor, FCKEditor, Expresso, Wyvern, Wygwam, Blogger's online editor, and ExpressionEngine's own built-in Rich Text Editor), pasted in from Microsoft Word, or imported from Adobe InDesign, ExSponge leaves it properly formatted and free of layout-breaking cruft.

It will also optionally remove all tags, or keep only the tags you want. And you can even trim the fully cleaned, cruft-free content down to a specified number of paragraphs.

This plugin is for developers who want neatly formatted paragraphs with minimal styling, and who do not want the proprietary tags and unnecessary parameters inserted by word processors (or the "tag soup" unwittingly generated by clients) compromising their layout.

Although undoubtedly less thorough than HTML TIDY or HTML Purifier, it is also more efficient, easier to set up, and focused on the specific problems you will likely encounter if you give your clients a WYSIWG field with which to edit their channel entries. In my worst-case scenario (a Microsoft Word document exported to HTML and pasted into an EE Rich Text field), ExSponge reduced the data size by 97% without any loss in content.

Some of what is removed by default:

* Word document garbage (including comments, proprietary styles, irrelevant formatting tags, etc.)
* Empty tag pairs (including empty paragraphs)
* Unnecessary or layout-breaking tags (html, iframe, head, style, center, form, object, etc.)
* Classes and styles within tags (unless otherwise specified)
* Non-printing and control characters
* Carriage returns and linefeeds
* Extra whitespace
* Empty lines
* JavaScript

In addition, special HTML entities are converted to their ASCII equivalents, special Word characters are converted to UTF-8, and non-breaking spaces (&nbsp;) are converted to normal spaces. Paragraph formatting is given special attention, and missing paragraph start and end tags are inserted where needed.

The final output will be compact, tidy, and ready to use in your layout.


PARAMETERS:

allow_breaks - Allow <br> tags to remain ("yes"), or consolidate them into paragraphs ("no"). Optional; default is "no".

allow_styles - Allow class and style parameters to remain ("yes"). Optional; default is "no".

allow_tags - Strip all HTML tags from the text ("no"), strip only <span> and <div> and <center> and form field tags ("yes"), or strip all tags except the ones you list ("<a><img><p><strong><em><ul><ol><li><h1><h2><h3><h4><h5>" for example would remove <blockquote> and <table>, among others, but would leave the most useful and safe tags intact). Optional; default is "yes".

convert_tags - Convert presentational tags <i> and <b> to the semantic <em> and <strong> ("yes"), or leave them as-is ("no"). Optional; default is "yes".

paragraphs - Clip the text after a specified number of paragraphs. Any positive number ("1", "4", "9999") will cause the text to be trimmed. Optional; default is "-1" (do not clip the text at all).


USAGE:

To use this plugin, simply wrap the text you want processed between these tag pairs:

{exp:ex_sponge}

	*** your mess goes here ***

{/exp:ex_sponge}

I typically use the above tag (with no parameters) in every template that contains the output of a Rich Text or WYSIWYG field the client is allowed to edit.

A more complex example:

{exp:ex_sponge allow_breaks="yes" convert_tags="no" allow_tags="<p><strong><b><em><i><ul><li>"}

	*** your mess goes here ***

{/exp:ex_sponge}

	<?php
		$buffer = ob_get_contents();

		ob_end_clean();

		return $buffer;
	}

	// --------------------------------------------------------------------

}
// END CLASS

/* End of file pi.ex_sponge.php */
/* Location: ./system/expressionengine/ex_sponge/pi.ex_sponge.php */