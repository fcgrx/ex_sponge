<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2013 FCGRX.
*/

$plugin_info = array(
						'pi_name'			=> 'ExSponge',
						'pi_version'		=> '0.9.0',
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

		$allow_breaks = ( ! $this->EE->TMPL->fetch_param('allow_breaks')) ? 'no' :  $this->EE->TMPL->fetch_param('allow_breaks');
		$allow_attributes = ( ! $this->EE->TMPL->fetch_param('allow_attributes')) ? 'no' :  trim($this->EE->TMPL->fetch_param('allow_attributes'));
		$convert_tags = ( ! $this->EE->TMPL->fetch_param('convert_tags')) ? 'yes' :  $this->EE->TMPL->fetch_param('convert_tags');
		$paragraphs = ( ! $this->EE->TMPL->fetch_param('paragraphs')) ? '-1' :  $this->EE->TMPL->fetch_param('paragraphs');
		$allow_tags = ( ! $this->EE->TMPL->fetch_param('allow_tags')) ? 'safe' :  $this->EE->TMPL->fetch_param('allow_tags');
		if ($allow_tags == 'safe')
			$allow_tags = "<p><br><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><dl><dt><dd><cite><table><tr><td><th><thead><tbody><tfoot>";
		elseif ($allow_tags == "minimal")
			$allow_tags = "<p><br><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote>";

		$str = ($str == '') ? $this->EE->TMPL->tagdata : $str;

		// MICROSOFT WORD CLEANUP 1
		// remove Word / HTML comments
		$str = preg_replace('#<!--(.*)-->#Uis','',$str);
		// remove C-type comments
		$str = preg_replace('#/\*.*?\*/#sm', '', $str);
		// remove title, head, style, script and form tags (and everything inside them)
		// NOTE: youTube video embedding requires: iframe, embed, param, object
		// NOTE: Amazon widgets require object, noscript
		// TO DO: ALLOW MORE CONTROL OVER THIS?
		$str = preg_replace('#<(title|head|style|form|script|object|applet|xml)[^>]*>.*</\1\s*>#Umis','',$str);

		// MALICIOUS SCRIPT REMOVAL
		$str = preg_replace('#javascript:([^><;]*?;)?#mis','',$str);

		// CHARACTER-LEVEL CLEANUP
		// remove non-printing / control characters
		$str = preg_replace('#[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]#','',$str);
		// fix dash entities
		$str = str_replace('Ã¢â‚¬â€œ','&mdash;',$str);
		// decode all entities (including &nbsp;); we assume UTF-8
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		// decode ellipsis, dash, quote, dot
		$str = str_replace(
				array("\\xe2\\x80\\xa6", "\\xe2\\x80\\x93", "\\xe2\\x80\\x94",
				"\\xe2\\x80\\x98", "\\xe2\\x80\\x99", "\\xe2\\x80\\x9c",
				"\\xe2\\x80\\x9d", "\\xe2\\x80\\xa2"),
				array('...','-','-','\\','\\','"','"','*'), $str );
		// fix wayward characters
		// [HOLDING THIS IN RESERVE PENDING FUTURE TESTING]
		// $str = str_replace( array('ì', 'î', 'í', 'ë'), array('"', '"', "'", "'"), $str );
		// remove Unicode non-breaking spaces inside paragraphs
		$str = urlencode($str);
		$str = str_replace ("%3Cp%3E%C2%A0%3C%2Fp%3E",'',$str);
		// TinyMCE can introduce these (to maintain empty paragraphs?)
		$str = str_replace ("%C2%A0"," ",$str);
		$str = urldecode($str);
		// Remove phantom &#8203; garbage that may be created by EE's rte
		$str = str_replace('​', '', $str); // NOTE: this is not an empty string!
		// convert ampersands back to &amp;
		$str = preg_replace('~&(?![\w][\w\+][\w]{0,5};)~Ui','&amp;$1',$str);

		// prevent the loss of arithmetic expressions ( 2<3 )
		$str = preg_replace('#<([0-9]+)#', ' < $1', $str);

		// CLEAN SLOPPY HTML 
		// (these problems usually caused by manual editing of HTML)
		// fix unclosed <img> tags
		$str = preg_replace('#(<img [^>/]*)/s*>#im','$1 />',$str);
		// close unterminated tags and unterminated quotes
		// (or strip_tags will delete everything that follows them)
		$str = preg_replace('#(<(?:/|\w+\b))([^>]*)(</?\w+\s*)#Um','$1>$2$3',$str);

		// MICROSOFT WORD CLEANUP 2
		// remove out-of-scope, layout-breaking, proprietary and meaningless tags
		// TO DO: GIVE MORE CONTROL OVER THIS?
		$str = preg_replace('#</?(\!|html|body|header|footer|section|article|link|iframe|frame|frameset|font|noscript|o:|v:|w:|x:|p:|st1:)[^>]*>#Umis','',$str);
		// remove proprietary v: o: w: x: p: st1: parameters from all tags
		$str = preg_replace('#(<\w[^>]*)\s*(?:v|o|w|x|p|st1):\w*=(?:"[^"]*"|\w+\b)([^>]*>)#Umi', "$1$2", $str);

		// SEMANTIC UPGRADE
		if ($convert_tags != 'no')
		{
			// upgrade deprecated and presentational tags to their semantic equivalent
			$search = array('#<(/?)i\b[^>]*>#ui', '#<(/?)b\b[^>]*>#ui', '#<(/?)s\b[^>]*>#ui', '#<(/?)strike\b[^>]*>#ui');
			$replace = array('<$1em>','<$1strong>','<$1del>','<$1ins>');
			$str = preg_replace($search, $replace, $str);
			// IN-BROWSER ENTRY FIELD CLEANUP
			// entry fields (with styleWithCSS turned on) can generate CSS styles;
			// insert semantic tags so important styling is not lost when we delete span tags
			$str = preg_replace('#(<span [^>]*style=[^<>]*font-weight:\s*bold[^><]*>)(.*)(</span>)#Umis','$1<strong>$2</strong>$3',$str);
			$str = preg_replace('#(<span [^>]*style=[^>]*font-style:\s*italic[^><]*>)(.*)(</span>)#Umis','$1<em>$2</em>$3',$str);
		}

		// wrap text in <p>, in case it's an unformatted (tag-free) text block
		// (we will clean this up below)
		if ($allow_tags != 'no')
			$str = "<p>$str</p>";

		// STRIP TAGS
		// before removing table tags, prepare table contents
		if (strpos($allow_tags, '<table>') === false)
		{
			$str = preg_replace('#(<th\b[^>]*>)(.*)(</th\s*>)#Umis', '$1<strong>$2</strong>$3', $str);
			$str = preg_replace('#(<tr\b[^>]*>)#Umi', '$1<br />', $str);
			$str = preg_replace('#(<(td|th)\s*>)#Umi', '$1 ', $str);
		}
		if ($allow_tags == 'no')
			$str = strip_tags($str);
		elseif ($allow_tags != 'yes')
			$str = strip_tags($str,$allow_tags);

		// REMOVE ATTRIBUTES
		if ($allow_attributes != 'yes')
		{
			// remove all attributes from paragraphs and elementary tags
			if ($allow_attributes == 'no')
				$str = preg_replace('#<(p|b|i|strong|em|br|h[1-6])\s[^>]*>#Umis', '<$1>', $str);

			if (empty($allow_attributes) || ($allow_attributes == 'no'))
			{
				$allow_attributes = 'href|src|height|width|alt|title|cite';
			}
			// repeat our filter until no matches found (or we need to move on)
			// TODO: SPEED TEST THIS FILTER AGAINST A FUNCTION CALL APPROACH
			$matches = 1;
			$limit = substr_count($allow_attributes,'|') + 1; 
			// NOTE: limit can be set to a high value (i.e. 7) to better ensure full removal, at cost of more processing time
			$limit = max(3,min($limit,10)); 
			while ($limit-- && ($matches > 0))
			{
				$str = preg_replace('~(<\w[^\s<>]*)((?:\s+[^=/]+(?<='.$allow_attributes.')=(?:"[^"]*"|\'[^\']*\'))*)((?:\s+[^=/]+(?<!'.$allow_attributes.')=(?:"[^"]*"|\'[^\']*\'))+)([^>]*>)~imx', "$1$2$4", $str, -1, $matches);
			}
		}

		// FILTER BR's
		// normalize BR's (convert <br/> and <br> to <br />)
		$str = preg_replace('#<br\s*/?>#im', "<br />", $str);
		// remove BR's at end of headers and list items and table tags
		$str = preg_replace('#(?:<br />\s*)+(</(h[1-6]|li|td|th|tr|table)>)#im', '$1',$str);
		// optionally convert BR's to paragraphs
		if ($allow_breaks == 'no')
		{
			$str = preg_replace('#(<br />\s*)+#im', '</p><p>',$str);
		}
		elseif ($allow_breaks == 'single')
		{
			$str = preg_replace('#(<br />\s*){2,}#im', '</p><p>',$str);
		}

		// CRUFT REMOVAL
		// fix tags that close and immediately reopen (i.e. </strong><strong>)
		$str = preg_replace('#</(b|strong|m|i|h[1-6])[^>]*>(\s*)<\1>#Umi','$2',$str);
		// remove images with empty src
		$str = preg_replace('#<img [^>]*src=\s*("\s*"|\'\s*\')[^>]*/?>#Umi','',$str);
		// remove images with no src
		$str = preg_replace('#<img\s*((?!src=).)*/?>#Umi','',$str);

		// CLEAN UP PARAGRAPHS
		// make sure all paragraphs are closed (we clean this up below)
		$str = preg_replace('#(<p\b[^>]*>)#Umi', '</p>$1', $str);
		// follow all end-of-paragraphs </p> with start-of-paragraph <p> (we clean this up below)
		$str = preg_replace('#</p\s*>#Umi', '</p><p>', $str);
		// remove BR's at the beginning of p's
		$str = preg_replace('#<p>\s*(<br />)+#im', '<p>', $str);
		// start paragraphs after other block level tags finish (we clean this up below)
		$str = preg_replace('#(</(h[1-6]|ol|ul|table)\s*>)#im', '$1<p>', $str);
		// end paragraphs before other block level tags start (we clean this up below)
		$str = preg_replace('#(<(h[1-6]|ol|ul|table)[^>]*>)#im', '</p>$1', $str);
		// remove paragraph tags from inside lists and headers (invalid syntax)
		$str = preg_replace('#(<(ol|ul|li|h[1-6]|table)[^>]*>[^<]*)(\s*</?p\b[^>]*>)+#im', '$1', $str);
		// consolidate sequential tags of items that can't nest (<p><p> to <p>)
		$str = preg_replace('#<(/?(?:p|h[1-6]|code|pre))>(\s*<\1>)+#im', '<$1>', $str);
		// remove empty paragraphs (and other empty tag pairs, including nested ones, incl. tags w/ attributes but no content)
		$str = preg_replace('#<(\w[^</>]*\b)([^</>]*)>([\s]*?|(?R))</\1>#Umis', '', $str);

		// WHITESPACE
		// compact all whitespace (and convert tabs & carriage returns to spaces)
		$str = trim(preg_replace('#\s\s*#m',' ',$str));
		// remove spaces before end tags for block-level elements (append a space to preserve word breaks)
		$str = preg_replace('#\s+((</(p|ul|ol|li|h[1-6]|table|tr|td|th)[^>]*>)+)\s*#im', '$1', $str);
		// remove extra whitespace at beginning / end of paragraph, header, list, etc
		$str = preg_replace('#\s*<(p|h[1-6]|ol|ul|li|table|tr|td|th)([^>]*)>\s*#im', '<$1$2>', $str);
		// remove whitespace from end of tags
		$str = preg_replace('#<(/?\w+[^>]*)\s+>#im', '<$1>', $str);
		// remove orphaned tags at beginning of text (i.e. </p> at beginning)
		$str = preg_replace('#^\s*</\w[^>]*>#A', '', $str);
		// remove orphaned tags (other than <img />) at end of text (i.e. <p> at end)
		$str = preg_replace('#<\w+[^/>]*\s*>\s*$#Dm', '', $str);

		// PRETTIFY
		// add newline before some tags
		$str = preg_replace('#(<(?:p|h[1-6]|/?ol|/?ul|li|/?table|/?thead|/?tbody|/?tfoot|tr)[^>]*>)#Umi', "\n$1",$str);
		// insert tab before some subordinate tags
		$str = preg_replace('#(<(?:li|tr)[^>]*>)#Umi', "\t$1",$str);

		// LIMIT PARAGRAPHS
		if ($paragraphs > 0)
		{
			$chunks = explode('<p>', $str);
			if ($chunks)
			{
				// if text starts with non-paragraph tags (ul, etc) don't include that as paragraph 1
				if (strpos($chunks[0],'<p') === false) $paragraphs++;
				$chunks = array_slice($chunks,0,$paragraphs);
				$str = implode('<p>', $chunks);
			}
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

This plugin cleans up the mess your clients leave behind!

Whether your markup was entered via WYSIWYG (Rich Text) editors (such as TinyMCE, CKEditor, FCKEditor, Expresso, Wyvern, Wygwam, Blogger's online editor, and ExpressionEngine's own built-in Rich Text Editor), pasted in from Microsoft Word or Adobe InDesign, or bulk-imported from XML or another CMS, ExSponge leaves it properly formatted and free of layout-breaking cruft.

It will also optionally remove all tags, or keep only the tags you want. And you can even trim the fully filtered, cruft-free content down to a specified number of paragraphs.

This plugin is for developers who want neatly formatted paragraphs with minimal, semantic styling, and who do not want the proprietary tags and unnecessary parameters inserted by word processors (or the "tag soup" unwittingly generated by clients) compromising their layout.

Although undoubtedly less comprehensive than HTML TIDY or HTML Purifier, it is also more efficient, easier to set up, and focused on the specific problems you will likely encounter if you give your clients a WYSIWG field with which to edit their channel entries. In my worst-case scenario (a Microsoft Word document exported to HTML and pasted into an EE Rich Text field), ExSponge reduced the data size by 97% without any loss in content.

Some of what is removed by default:

* Word document garbage (including comments, proprietary styles, useless XML tags, "smart" tags, etc.)
* Empty tags (including empty paragraphs, unnecessary tag pairs like <strong></strong>, etc.)
* Out-of-scope sections (head, title, style, form, script, object, applet, xml)
* Unnecessary or layout-breaking tags (html, head, iframe, object, center, etc.)
* Unnecessary parameters within tags (unless otherwise specified)
* Inline styling (unless otherwise specified)
* JavaScript (including malicious code)
* Non-printing and control characters
* Newlines (\n) and linefeeds (\r)
* Images with no source
* Extra whitespace
* Anchor links
* Empty lines
* PHP

In addition, special HTML entities are converted to their ASCII equivalents, special Word characters are converted to UTF-8, unterminated tags are closed, and non-breaking spaces (&nbsp;) are converted to normal spaces. If tables tags are to be removed, table text is reformatted first. Paragraph formatting is given special attention, and missing paragraph start and end tags are inserted where needed.

The final output will be compact, tidy, and ready to use in your layout.


PARAMETERS:

allow_tags - Strip all HTML tags from the text ("no"), strip most tags but keep the most useful and safe ("safe", which is the equivalent of "<p><br><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><dl><dt><dd><cite><table><tr><td><th><thead><tbody><tfoot>"), strip most tags but the minimum ("minimal", which is the equivalent of "<p><br><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote>"), or strip all tags except the ones you list. Tip: if you set this parameter to "<p>", text will be reduced to paragraphs only. Note that out-of-scope tags (html, head, link, header, footer etc) will be removed regardless. Optional; default is "safe".

allow_breaks - Allow <br> tags to remain as-is ("yes"), or convert double-breaks ("<br><br>") to paragraphs while leaving single breaks alone ("single"), or consolidate all breaks into paragraphs ("no"). Optional; default is "no".

allow_attributes - Allow tag attributes to remain ("yes"), strip all but the most necessary ("no", which is the equivalent of "href|src|height|width|alt|title|cite"), or strip all attributes except the ones you list. Optional; default is "no".

convert_tags - Convert presentational tags <i> and <b> and <s> and <strike> to the semantic <em> and <strong> and <del> and <ins> ("yes") or leave them as-is ("no"). Optional; default is "yes".

paragraphs - Clip all text after a specified number of paragraphs. Any positive number ("1", "4", "9999") will cause the text to be trimmed. Optional; default is "-1" (do not clip the text at all).

NOTE: allow_styles parameter removed as of v0.9; it is redundant since the addition of the more flexible allow_attributes parameter


USAGE:

To use this plugin, simply wrap the text you want processed between these tag pairs:

{exp:ex_sponge}

	*** your mess goes here ***

{/exp:ex_sponge}

In my templates, I typically wrap the above tag (with no parameters) around the output of any Rich Text or WYSIWYG field the client is allowed to edit.

A more complex example, which reduces the markup down to the basics, keeps only the first four paragraphs, and takes advantage of EE's built-in tag caching:

{exp:ex_sponge allow_tags="<p><strong><em><ul><li>" allow_attributes="href|src|alt|title" paragraphs="4" cache="yes" refresh="1440"}

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