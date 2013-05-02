<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2013 FCGRX.
*/

$plugin_info = array(
						'pi_name'			=> 'ExSponge',
						'pi_version'		=> '0.9.1',
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
	public function Ex_sponge($str = '')
	{
		$this->EE =& get_instance();

		$allow_breaks = ( ! $this->EE->TMPL->fetch_param('allow_breaks')) ? 'no' :  $this->EE->TMPL->fetch_param('allow_breaks');
		$allow_attributes = ( ! $this->EE->TMPL->fetch_param('allow_attributes')) ? 'no' :  trim(trim($this->EE->TMPL->fetch_param('allow_attributes')),'|');
		$convert_tags = ( ! $this->EE->TMPL->fetch_param('convert_tags')) ? 'yes' :  $this->EE->TMPL->fetch_param('convert_tags');
		$paragraphs = ( ! $this->EE->TMPL->fetch_param('paragraphs')) ? '-1' :  $this->EE->TMPL->fetch_param('paragraphs');
		$allow_tags = ( ! $this->EE->TMPL->fetch_param('allow_tags')) ? 'minimal' :  trim($this->EE->TMPL->fetch_param('allow_tags'));
		if ($allow_tags == 'safe')
			$allow_tags = "<p><br><b><a><i><em><strong><del><ins><u><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><q><sup><sub><dl><dt><dd><cite><table><tr><td><th><thead><tbody><tfoot>";
		elseif ($allow_tags == "minimal")
			$allow_tags = "<p><br><b><a><i><em><strong><del><ins><u><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><q><sup><sub>";

		// allow_tags can override our default purging of certain code blocks
		$disallowed_block_tags = 'title|head|base|style|form|script|object|applet|xml';
		// todo - change all to all-body and make all really all tags (except word crap)
		if ($allow_tags == 'all') $disallowed_block_tags = "";
		else
		{
			$allowed_block_tags = trim(str_replace('><','|', $allow_tags),'<>');
			$disallowed_block_tags = trim(preg_replace('#((^|\|)('.$allowed_block_tags.')\b)#mi', '', $disallowed_block_tags),'|';
		}

		$str = ($str == '') ? $this->EE->TMPL->tagdata : $str;

		// MICROSOFT WORD CLEANUP 1
		// remove Word / HTML comments
		$str = preg_replace('#<!--(.*)-->#Uis','',$str);
		// remove C-type comments
		$str = preg_replace('#/\*.*?\*/#sm', '', $str);
		// provided they are not listed in allow_tags, we remove certain code blocks
		// NOTE: youTube video embedding requires: iframe, embed, param, object
		// NOTE: Amazon widgets require object, noscript
		$str = preg_replace('#<('.$disallowed_block_tags.')[^>]*>.*</\1\s*>#Umis','',$str);
$this->EE->benchmark->mark('w3');
		// filter out all tags starting with <? (PHP, XML etc) or <! (DOCTYPE etc)
		$str = preg_replace('#<[\!\?][^>]*>#Umis','',$str);
		// fix unquoted non-alphanumeric characters in table tags
		$str = preg_replace('#<(table|td|th\s*)(width=)(\w+)\b#Umi','<$1$2"$3"',$str);

		// MALICIOUS SCRIPT REMOVAL
		// javascript embedded within tags
		$str = preg_replace('#(<[^>]*)javascript:([^>]*>)#mi','$1$2',$str);

		// CHARACTER-LEVEL CLEANUP
		// NOTE: HTML files created by Word are NOT saved with UTF-8 encoding by default (probably saved Windows-1252)
		// (This creates transcoding errors that propagate badly & result in weird characters which we try to fix below)
		// SEE: http://www.cs.tut.fi/~jkorpela/www/windows-chars.html
		// remove non-printing / control characters (except tabs)
//		$str = preg_replace('#[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]#','',$str);
		// decode ellipsis, dash, double smart quote, single smart quote, dot from UTF-8, zero-width space
		$str = str_replace(
				array("\\xe2\\x80\\xa6", "\\xe2\\x80\\x93", "\\xe2\\x80\\x94",
				"\\xe2\\x80\\x98", "\\xe2\\x80\\x99", "\\xe2\\x80\\x9c",
				"\\xe2\\x80\\x9d", "\\xe2\\x80\\xa2", "\\xe2\\x80\\x8b"),
				array('...','&ndash;','&mdash;',"'","'",'"','"','*',''), $str);
		// correct smart quotes incorrectly converted to multibyte, and other improperly transcoded characters
		$str = str_replace(
				array('Ã¢â‚¬â€œ','â€˜','â€™','â€œ','â€','â€¦','â€”','â€“','ì', 'î', 'í', 'ë','­','​'),
				array('&mdash;',"'","'",'"','"','...','&ndash;','&mdash;','"', '"', "'", "'",'-',''), $str);
		// convert properly transcoded smart quotes (and entities) to regular quotes
		$str = str_replace(
				array('“','”','‘','’','&ldquo;','&rdquo;','&lsquo;','&rsquo;','…'),
				array('"','"',"'","'",'"','"',"'","'",'...'), $str);
		// normalize numerically rendered entities
 		$str = str_replace(
				array('&#038;','&#060;','&#062;'),
				array('&amp;','&lt;','&gt;'), $str);
 		// normalize numerically rendered Unicode punctuation
		$str = str_replace(
			array('&#8203;','&#8208;','&#8209;','&#8211;', '&#8212;', '&#8213;','&#8214;', '&#8215;','&#8216;','&#8217;','&#8218;','&#8219;','&#8220;','&#8221;','&#8222;','&#8223;','&#8226;','&#8227;','&#8228;','&#8229;','&#8230;','&#8231;'),
			array('','-','-','--','&mdash;','--','||','<u>_</u>','`',"'",',','`','"','"',',,','"','&#183;','&#183;','&#183;','..','...','&#183;'), $str);
		// convert ampersands back to &amp;
		$str = preg_replace('/\&(?![\w#][\w\+][\w]{0,5};)/U','&amp;$1',$str);
		// NON-BREAKING SPACES
		// Word and WYSIWYG editors use these to force empty paragraphs and inflexible layout to persist
		$str = str_replace('&nbsp;', ' ', $str);
		// remove Unicode encoded non-breaking spaces too
/* THE LINE BELOW CAUSES THE WHOLE SCRIPT TO RETURN EMPTY STRING
		$str = preg_replace('#[\xC2\xA0]#','',$str);
*/
		// TEMP REPLACEMENT OF ABOVE LINE:
		$str = urlencode($str);
		$str = str_replace ("%C2%A0"," ",$str);
		$str = urldecode($str);

		// PREPARE FOR PATTERN MANIPULATIONS
		// convert < to entities where appropriate (so they arent confused for tags)
		// also prevent the loss of arithmetic expressions ( 2<3 )
		$str = preg_replace('#<(?=[<\s0-9])#m','&lt;', $str);
		// close unterminated tags and unterminated quotes
		// (or strip_tags will delete everything that follows them)
		$str = preg_replace('#(<(?:/|\w+\b))([^>]*)(</?\w+\s*)#Um','$1>$2$3',$str);

		// CLEAN SLOPPY HTML 
		// (these problems usually caused by manual editing of HTML)
		// fix unclosed <img> tags
		$str = preg_replace('#(<img [^>/]*)/s*>#im','$1 />',$str);

		// CONVERT TAGS (SEMANTIC UPGRADE)
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

			// CONVERT MS WORD FONT SIZE TAGS TO HTML HEADER TAGS
			$str = preg_replace_callback('#<strong><font size="([23456])">(.*)</font></strong>#Umis',
				create_function('$matches','return "<h".(7-$matches[1]).">".$matches[2]."</h".(7-$matches[1]).">";'),$str);
		}

		// wrap text in <p>, in case it's an unformatted (tag-free) text block
		// (we will clean this up below)
		if ($allow_tags != 'no')
			$str = "<p>$str</p>";

		// STRIP TAGS
		// before removing table tags, prepare <table> cell contents
		if (strpos($allow_tags, '<table>') === false)
		{
			$str = preg_replace('#(<th\b[^>]*>)(.*)(</th\s*>)#Umis', '$1<strong>$2</strong>$3', $str);
			$str = preg_replace('#(</tr\b[^>]*>)#Umi', '$1<br />', $str);
			$str = preg_replace('#(<(td|th)\s*>)#Umi', '$1 ', $str);
		}
		// before removing div's, preserve layout by inserting a <br>
		// TO DO: handle all block-level tags similarly (blockquote)
		if (strpos($allow_tags, '<div>') === false)
		{
			$str = str_replace('</div>', '</div><br />', $str);
		}
		if ($allow_tags == 'no')
			$str = strip_tags($str);
		elseif ($allow_tags != 'yes')
			$str = strip_tags($str,$allow_tags);
		elseif ($allow_tags == 'yes')
		{
			// if allowing all tags, we still remove MS Word proprietary ones
			$str = preg_replace('#</?(o:|v:|w:|x:|p:|st1:)[^>]*>#Umi','',$str);
		}

		// REMOVE ATTRIBUTES
		if ($allow_attributes != 'yes')
		{
			// first pass: remove all attributes from paragraphs and elementary tags
			if ($allow_attributes == 'no')
				$str = preg_replace('#<(p|b|i|strong|em|del|ins|u|br|h[1-6])\s[^>]*>#Umis', '<$1>', $str);

			if (empty($allow_attributes) || ($allow_attributes == 'no'))
			{
				$allow_attributes = 'href|src|height|width|alt|title|name|cite|colspan';
			}
			// repeat our filter until no matches found (or we need to move on)
			// TODO: SPEED TEST THIS FILTER AGAINST A FUNCTION CALL APPROACH
			$matches = 1;
			$limit = substr_count($allow_attributes,'|') + 1; 
			// NOTE: limit can be set to a high value (i.e. 7) to better ensure full removal, at cost of more processing time
			$limit = max(3,min($limit,10)); 
			while ($limit-- && ($matches > 0))
			{
				$str = preg_replace('~(<\w[^\s<>]*)((?:\s+[^=/]+(?<='.$allow_attributes.')\s*=\s*(?:"[^"]*"|\'[^\']*\'|\w+\b))*)((?:\s+[^=/]+(?<!'.$allow_attributes.')\s*=\s*(?:"[^"]*"|\'[^\']*\'|\w+\b))+)([^>]*>)~imx', "$1$2$4", $str, -1, $matches);
			}
		}
		else
		{
			// even if keeping all attributes, remove MS Word proprietary v: o: w: x: p: st1:
			$str = preg_replace('#(<\w[^>]*)\s(?:v|o|w|x|p|st1):\w*=(?:"[^"]*"|\w+\b)([^>]*>)#Umi', "$1$2", $str);
		}
		// remove word mso- properties from style attributes
		if ($allow_attributes != 'no')
		{
			$str = preg_replace_callback("#(<\w[^>]+style=('|\"))([^\\2]+)(\\2[^>]*>)#Umi",
				create_function('$matches','return $matches[1].preg_replace("#\s*\bmso-[^:]+:\s*[^;]+(;\s*|$)#Umi", "", $matches[3]).$matches[4];'),$str);
		}

		// FILTER BR's
		// normalize BR's (convert <br/> and <br> to <br />)
		$str = preg_replace('#\s*<br\s*/?>\s*#im', "<br />", $str);
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
		$str = preg_replace('#</(b|strong|em|i|del|ins|u|h[1-6])\b[^>]*>(\s*)<\1>#Umi','$2',$str);
		// fix nested formatting tags (i.e. <strong><strong>stuff</strong></strong>)
		$str = preg_replace('#<((b|strong|em|i|del|ins|u|h[1-6])\b[^>]*)><\2\b[^>]*>([^<]*)</\2\s*></\2\s*>#Umi','<$1>$3</$2>',$str);
		// remove tags that require attributes but have none
		$str = preg_replace('#<(img|meta)\s*/?>#Umi','',$str);
		// remove tags that are meaningless without content, and have none
		// note: to disallow anchor links, add a to the list below
		$str = preg_replace('#<(b|strong|em|i|del|ins|u|h[1-6])\b\s*>(\s*)</\1\s*>#Umi','$2',$str);
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
		$str = preg_replace('#<p\b[^>]*>\s*(<br />)+#im', '<p>', $str);
		// start paragraphs after other block level tags finish (we clean this up below)
		$str = preg_replace('#(<(/h[1-6]|/ol|/ul|blockquote)[^>]*>)#im', '$1<p>', $str);
		// end paragraphs before other block level tags start (we clean this up below)
		$str = preg_replace('#(<(h[1-6]|ol|ul|table|/blockquote)[^>]*>)#im', '</p>$1', $str);
		// remove paragraph tags from inside lists and headers (invalid syntax) and tables
		$str = preg_replace('#(<(ol|ul|li|h[1-6]|body)[^>]*>[^<]*)(\s*</?p\b[^>]*>)+#im', '$1', $str);
		$str = preg_replace('#(</?p\b[^>]*>\s*)+(</(ol|ul|li|h[1-6])[^>]*>[^<]*)+#im', '$2', $str);
		// remove end paragraphs from after tables 
		// TODO: consolidate with similar block level tags
		$str = preg_replace('#(</(table|head|body|meta|style)>)\s*</p\s*>#Umi', '$1', $str);
		// remove all paragraph tags from inside tables
		$str = preg_replace_callback("#(<table[^>]*>)(.*)(</table\s*>)#Umis",
		create_function('$matches','return $matches[1].preg_replace("#</?p\b[^>]*>#Umi", "", $matches[2]).$matches[3];'),$str);
		// consolidate sequential tags of items that can't nest (<p><p> to <p>)
		$str = preg_replace('#<(/?(?:p|h[1-6]|code|pre)[^>]*)>(\s*<\1[^>]*>)+#im', '<$1>', $str);
		// remove empty paragraphs (and other empty tag pairs, including nested ones, incl. tags w/ attributes but no content)
		$str = preg_replace('#<(\w[^</>]*\b)([^</>]*)>([\s]*?|(?R))</\1>#Umis', '', $str);

		// WHITESPACE
		// compact all whitespace (and convert tabs & carriage returns to spaces)
		$str = preg_replace('#\s\s*#m',' ',$str);
		// remove spaces around end tags of block-level elements
		$str = preg_replace('#\s+((</(p|ul|ol|li|h[1-6]|table|tr|td|th|thead|tbody|tfoot)[^>]*>)+)\s*#im', '$1', $str);
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
		$str = preg_replace('#(<(p|h[1-6]|/?ol|/?ul|li|/?blockquote|/?table|/?thead|/?tbody|/?tfoot|/?tr|td|/?style|/?head|/?body|meta|title|form)[^>]*>)#Umi', PHP_EOL."$1",$str);
		// insert tab before some subordinate tags
		$str = preg_replace('#(<(li|/?tr|td)[^>]*>)#Umi', "\t$1",$str);
		// insert second tab before some subordinate tags
		$str = preg_replace('#(<(td)[^>]*>)#Umi', "\t$1",$str);

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

This plugin cleans up the mess your clients (and other filters) leave behind!

Whether your markup was originally entered via WYSIWYG (Rich Text) editors (such as TinyMCE, CKEditor, FCKEditor, Expresso, Wyvern, Wygwam, Blogger's online editor, and ExpressionEngine's own built-in Rich Text Editor), pasted in from Microsoft Word or Adobe InDesign, or bulk-imported from XML or another CMS, ExSponge leaves it properly formatted and free of layout-breaking cruft.

It will also optionally remove all tags, or keep only the tags you want. Limit tag parameters too. And you can even trim the fully filtered, cruft-free content down to a specified number of paragraphs.

This plugin is for developers who want neatly formatted paragraphs with minimal, semantic styling, and who do not want the proprietary tags and unnecessary parameters inserted by word processors (or the "tag soup" unwittingly generated by clients) compromising their layout.

Although undoubtedly less comprehensive than HTML TIDY or HTML Purifier, it is also more efficient, easier to set up, and focused on the specific problems you will likely encounter if you give your clients a WYSIWG field with which to edit their channel entries. Especially if they are composing in Word and pasting the content in. In my worst-case scenario (a Microsoft Word document exported to HTML and pasted into an EE Rich Text field), ExSponge reduced the data size by 97% in without any loss in content.

ExSponge is not just a real-time (inline) cleaner for text markup. Used with an importing routine, it can clean up markup exported from Blogger or WordPress. And with a little Ajax, it can clean code entered in your SafeCracker forms before they hit your database (set up a simple template that sends your text through the ExSponge filter, and call it via Ajax before submitting the form; more details on that will be added here soon).

Some of what is removed by default:

* Word document garbage (including comments, proprietary styles, useless XML tags, "smart" tags, etc.)
* Empty tags (including empty paragraphs, unnecessary tag pairs like <strong></strong>, etc.)
* Purposefully empty paragraphs that WYSIWYG editors are so fond of (<p>&nbsp;</p>, etc.)
* Out-of-scope sections (head, title, style, form, script, object, applet, xml)
* Unnecessary or layout-breaking tags (html, head, iframe, object, center, etc.)
* Unnecessary parameters within tags (unless otherwise specified)
* Inline styling (unless otherwise specified)
* JavaScript (including malicious code)
* Non-printing and control characters
* Newlines (\n) and linefeeds (\r)
* Images with no source
* Extra whitespace
* Zero-width spaces
* Empty lines
* PHP

In addition, ExSponge will:

* Convert oddball characters and entities to the appropriate web-safe ASCII equivalent or entity
* Convert ampersands to entities where appropriate (including inside URLs)
* Convert smart quotes (curly quotes) to normal quotes
* Close unterminated tags and quotes
* Convert non-breaking spaces (&nbsp;) to normal spaces
* Normalize all tags to lowercase
* Reformat table text to be readable (if tables tags are to be removed)
* Give special attention to paragraph formatting, and insert missing paragraph start and end tags
* Prettify the output (with newlines and tabs)

The final output will be compact, tidy, and ready to use in your layout.


PARAMETERS:

NOTE: All parameters are optional. See the Usage section for an example that requires only two lines of template code.

allow_tags - Remove all HTML tags from the markup and leave only raw, unformatted text ("no"), strip most tags but keep the most useful and safe ("safe", which is the equivalent of "<p><br><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><q><dl><dt><dd><cite><table><tr><td><th><thead><tbody><tfoot>"), strip most tags but the minimum ("minimal", which is the equivalent of "<p><br><b><a><i><em><strong><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><q>"), or strip all tags except the ones you list. Tip: if you set this parameter to "<p>", text will be reduced to paragraphs only. Note that out-of-scope tags (html, head, link, header, footer etc) will be removed regardless. Optional; default is "minimal".

allow_breaks - Allow <br> tags to remain as-is ("yes"), or convert double-breaks ("<br><br>") to paragraphs while leaving single breaks alone ("single"), or consolidate all breaks into paragraphs ("no"). Optional; default is "no".

allow_attributes - Allow tag attributes to remain ("yes"), strip all but the most necessary ("no", which is the equivalent of "href|src|height|width|alt|title|cite"), or strip all attributes except the ones you list. Optional; default is "no".

convert_tags - Convert presentational tags <i> and <b> and <s> and <strike> to the semantic <em> and <strong> and <del> and <ins>, and convert Word font size tags to HTML header tags ("yes") or leave all of them as-is ("no"). Optional; default is "yes".

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