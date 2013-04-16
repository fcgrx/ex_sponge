<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2013 FCGRX.
*/

$plugin_info = array(
						'pi_name'			=> 'ExSponge',
						'pi_version'		=> '0.8.5',
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

		$allow_tags = ( ! $this->EE->TMPL->fetch_param('allow_tags')) ? 'safe' :  $this->EE->TMPL->fetch_param('allow_tags');
		$allow_breaks = ( ! $this->EE->TMPL->fetch_param('allow_breaks')) ? 'no' :  $this->EE->TMPL->fetch_param('allow_breaks');
		$allow_styles = ( ! $this->EE->TMPL->fetch_param('allow_styles')) ? 'no' :  $this->EE->TMPL->fetch_param('allow_styles');
		$convert_tags = ( ! $this->EE->TMPL->fetch_param('convert_tags')) ? 'yes' :  $this->EE->TMPL->fetch_param('convert_tags');
		$paragraphs = ( ! $this->EE->TMPL->fetch_param('paragraphs')) ? '-1' :  $this->EE->TMPL->fetch_param('paragraphs');

		$str = ($str == '') ? $this->EE->TMPL->tagdata : $str;

		// MICROSOFT WORD CLEANUP
		// remove non-printing / control characters
		$str = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/','',$str);
		// fix mistranslated entities
		$search = array('/&amp;QUOT;/iu', '/&#47;/u');
		$replace = array('&quot;', '/');
		$str = preg_replace($search, $replace, $str);
		// decode all other entities (including &nbsp;)
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		// decode ellipsis, dash, quote, dot; fix wayward characters
		$str = str_replace(
				array("\\xe2\\x80\\xa6", "\\xe2\\x80\\x93", "\\xe2\\x80\\x94", 
				"\\xe2\\x80\\x98", "\\xe2\\x80\\x99", "\\xe2\\x80\\x9c", 
				"\\xe2\\x80\\x9d", "\\xe2\\x80\\xa2"), 
				array('...','-','-','\\','\\','"','"','*'), $str );
		// fix wayward characters
		// [HOLDING THIS IN RESERVE PENDING FUTURE TESTING]
		//$str = str_replace( array('ì', 'î', 'í', 'ë'), array('"', '"', "'", "'"), $str );
		// remove title, head, style, script and form tags (and everything inside them)
		$str = preg_replace('#<(title|head|style|form|script|object|applet|xml)[^>]*>.*</\1>#imUs','',$str);
		// remove Word / HTML comments
		$str = preg_replace('/<!--(.*)-->/Uis','',$str);
		// remove C-type comments
		$str = mb_eregi_replace('#/\*.*?\*/#s', '', $str, 'm');
		// remove layout-breaking, proprietary and meaningless tags
		// TO DO: GIVE MORE CONTROL OVER THIS?
		$str = preg_replace('#</?(\!|html|body|head|style|meta|link|iframe|frame|frameset|font|del|ins|o:|v:|w:|x:|p:)[^>]*>#imxsU','',$str);
		if ($allow_styles != 'yes')
		{
			// remove all parameters from paragraphs and elementary tags
			$str = preg_replace('/<(p|b|i|strong|em|br)\s[^>]*>/imsU', '<$1>', $str);
			// remove styling from all tags
			$str = preg_replace('#(<[^>]+\s*)(style|class|lang|size|face|align|font|onmouseover|onclick)=("[^"]+"|\'[^\']+\'|[^\'">]+\w)([^>]*>)#imU', "$1$4", $str);
			// BLOGGER CLEANUP
			// remove target and onclick parameters Blogger inserts into links
			$str = preg_replace('#<a\s+[^>]*(href="[^"]*")[^>]+>#imU', '<a $1>', $str);
		}
		// remove proprietary v: o: w: x: p: parameters from all tags
		$str = preg_replace('#(<[^>]+)(v|o|w|x|p):[^=>]*="[^"]*"([^>]*>)#imU', "$1$3", $str);
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

		// FIX WEIRD PROBLEMS THAT ACTUALLY HAPPEN
		// Odd characters can make empty paragraphs look non-empty.
		// It seems the only way to remove them to target <p>%C2%A0</p>
		$str = urlencode($str);
		$str = str_replace ("%3Cp%3E%C2%A0%3C%2Fp%3E","",$str);
		// TinyMCE can introduce these too
		$str = str_replace ("%C2%A0"," ",$str);
		$str = urldecode($str);

		// EE RTE CLEANUP
		// Remove phantom &#8203; garbage that may be created by EE's rte
		$str = str_replace('​', '', $str); // NOTE: this is not an empty string!

// IN-BROWSER ENTRY FIELD CLEANUP
// entry fields (with styleWithCSS turned on) can generate CSS styles;
// insert semantic tags so important styling is not lost
$str = preg_replace('#(<span [^>]*style=)(\'|")([^\'">]*font-weight:\s*bold[^>]*>)(.*)</span>#imsx','$1$2$3<strong>$4</strong></span>',$str);
$str = preg_replace('#(<span [^>]*style=)(\'|")([^\'">]*font-style:\s*italic[^>]*>)(.*)</span>#imsx','$1$2$3<em>$4</em></span>',$str);

		// wrap text in <p>, in case it's an unformatted (tag-free) text block
		// (we will clean this up below)
		$str = "<p>$str</p>";

		// prevent the loss of arithmetic expressions ( 2<3 ) before stripping tags
		$str = preg_replace('/<([0-9]+)/', ' < $1', $str);

		// STRIP TAGS
		if ($allow_tags == 'safe')
			$str = strip_tags($str, "<p><br><br /><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><dl><dt><dd><cite><code>");
		elseif ($allow_tags != strip_tags($allow_tags))
			$str = strip_tags($str,$allow_tags);
		else
			$str = strip_tags($str);

		// BR's
		// normalize BR's
		$str = str_replace("<br>", "<br />", $str);
		// remove BR's in headers
		$str = preg_replace('#(<br />)\s*(</(h[1-6])>)#im', '$2',$str);
		 // remove BR's from lists
		$str = preg_replace('#(<br />)\s*(</(li)>)#im', '$2',$str);
		// optionally change all (remaining) BR's to paragraphs
		if ($allow_breaks == 'no')
		{
			$str = preg_replace('#(<br />\s*)+#mi', '</p><p>',$str);
		}

		// WHITESPACE
		// fix tags that close and immediately reopen (i.e. </strong><strong>)
		$str = preg_replace('#</(b|strong|em|i|h[1-6])[^>]*>\s*<\1>#imU',' ',$str);
		// remove spaces before end tags for block-level elements
		// (append a space to preserve word breaks)
		$str = preg_replace('#\s+((</(p|ul|ol|li|h[1-6])[^>]*>)+)\s*#m', '$1 ', $str);
		// compact all whitespace (and convert tabs & carriage returns to spaces)
		$str = trim(preg_replace('#\s\s*#m',' ',$str));
		// remove extra whitespace at beginning of paragraph, header, list, etc
		$str = preg_replace('#\s*<(p|h[1-6]|ol|ul|li)>\s*#im', '<$1>', $str);

		// CLEAN UP PARAGRAPHS
		// make sure all paragraphs are closed (we clean this up below)
		$str = str_replace('<p>', '</p><p>', $str);
		// remove BR's at the beginning of p's
		$str = preg_replace('#<p>\s*(<br />)+#im', '<p>', $str);
		// start paragraphs after other block level tags finish (we clean this up below)
		$str = preg_replace('#(</(h[1-6]|ol|ul)>)#im', '$1<p>', $str);
		// end paragraphs before other block level tags start (we clean this up below)
		$str = preg_replace('#(<(h[1-6]|ol|ul)[^>]*>)#im', '</p>$1', $str);
		// change double opening P's to single opening P's
		$str = preg_replace('#(<p>\s*)+#im', '<p>', $str);
		// change double closing P's to single closing P's
		$str = preg_replace('#(</p>\s*)+#im', '</p>', $str);
		// remove empty paragraphs (and other empty tag pairs)
		$str = preg_replace('#<([^</>]*)>([\s]*?|(?R))</\1>#imsU', '', $str);

		// remove orphaned tags at beginning of text (i.e. </p> at beginning)
		$str = preg_replace('#^\s*</[^>]+>#A', '', $str);
		// remove orphaned tags (other than <img />) at end of text (i.e. <p> at end)
		$str = preg_replace('#<[^/>]+[^/]>\s*$#mU', '', $str);

		// PRETTIFY
		// add carriage return before/after some tags
		$str = preg_replace('#(<(p|h[1-6]|/?ol|/?ul|li)>)#i', "\n$1",$str);
		// insert tab before some tags
		$str = preg_replace('#(<(li)>)#i', "\t$1",$str);

		if ($paragraphs > 0)
		{
			$chunks = explode('<p>', $str);
			$chunks = array_slice($chunks,0,$paragraphs);
			$str = implode('<p>', $chunks);
		}

 		$this->return_data = "\n".trim($str)."\n";
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

This plugin cleans up the mess your clients leave behind!

Whether your content was entered via WYSIWYG (Rich Text) editors (such as TinyMCE, CKEditor, FCKEditor, Expresso, Wyvern, Wygwam, Blogger's online editor, and ExpressionEngine's own built-in Rich Text Editor), pasted in from Microsoft Word, or imported from Adobe InDesign, ExSponge leaves it properly formatted and free of layout-breaking cruft.

It will also optionally remove all tags, or keep only the tags you want. And you can even trim the fully filtered, cruft-free content down to a specified number of paragraphs.

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
* XML

In addition, special HTML entities are converted to their ASCII equivalents, special Word characters are converted to UTF-8, and non-breaking spaces (&nbsp;) are converted to normal spaces. Paragraph formatting is given special attention, and missing paragraph start and end tags are inserted where needed.

The final output will be compact, tidy, and ready to use in your layout.


PARAMETERS:

allow_breaks - Allow <br> tags to remain ("yes"), or consolidate them into paragraphs ("no"). Optional; default is "no".

allow_styles - Allow class and style parameters to remain ("yes"). Optional; default is "no".

allow_tags - Strip all HTML tags from the text ("no"), strip most tags but keep the most useful and safe ("safe", which is the equivalent of "<a><i><em><strong><cite><code><ul><ol><li><dl><dt><dd><img><h1><h2><h3><h4><h5><h6><br><p><b><blockquote>"), or strip all tags except the ones you list. Optional; default is "safe".

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