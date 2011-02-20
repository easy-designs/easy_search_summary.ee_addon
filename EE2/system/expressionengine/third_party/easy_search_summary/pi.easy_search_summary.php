<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Easy_search_summary Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Aaron Gustafson
 * @copyright		Copyright (c) Easy! Designs, LLC
 * @link			http://www.easy-designs.net/
 */

$plugin_info = array(
	'pi_name'        => 'Easy Search Summary',
	'pi_version'     => '1.0',
	'pi_author'      => 'Aaron Gustafson',
	'pi_author_url'	 => 'http://easy-designs.net/',
	'pi_description' => 'Creates a summary from content, based on keywords',
	'pi_usage'       => Easy_search_summary::usage()
);

class Easy_search_summary {

	var $return_data;
	var $keywords;

	# the HTML needed for output
	var $term = '<{tag} class="{class}">{keyword}</{tag}>';

	/**
	* PHP4 Constructor
	*
	* @see	__construct()
	*/
	function Easy_search_summary( $str='', $keywords=array(), $html_ver=5, $class=FALSE )
	{
		$this->__construct( $str, $keywords, $html_ver, $class );
	} # end Easy_search_summary constructor

	// --------------------------------------------------------------------

	/**
	* PHP5 Constructor
	*
	* @return	void
	*/
	function __construct( $str='', $keywords=array(), $html_ver=5, $class=FALSE )
	{
		$this->EE =& get_instance();
		
		# get the hash
		$hash = FALSE;
		if ( !count( $keywords ) && !$hash )
		{
			$hash = ( $temp = $this->EE->TMPL->fetch_param('hash') ) ? $temp : array();
		}
		# determine the tag to use
		if ( $temp = $this->EE->TMPL->fetch_param('html_version') ) $html_ver = $temp;
		$tag = $html_ver==5 ? 'mark' : 'strong';
		# determine the class of the keywords
		if ( ! $class ) $class = ( $temp = $this->EE->TMPL->fetch_param('class') ) ? $temp : 'term';
		# any alternate content?
		$alternates = array();
		$i = 0;
		while ( $i++ < 10 )
		{
			if ( $temp = $this->EE->TMPL->fetch_param('alternate_'.$i) ) $alternates[] = $temp;
		}
		
		# find the keywords
		if ( $hash )
		{
			if ( strlen( $hash ) > 32 ) $hash = substr( $hash, 0, 32 );
			$keywords = $this->EE->db->query(
				"SELECT `keywords`
				 FROM   `exp_search`
				 WHERE  `search_id` = '{$this->EE->db->escape_str($hash)}'"
			)->row('keywords');
		}

		# manage the keywords
		$keywords = explode( ' ', str_replace( '+', '', $keywords ) );
		$i     = 0;
		$temp  = array();
		$mult  = FALSE;
		$quote = FALSE;
		foreach ( $keywords as $k )
		{
			$length = strlen( $k );
			if ( strpos( $k, '-' ) === 0 )
			{
				unset( $k );
			}
			else
			{
				if ( ! $mult && ( strpos( $k, '"' ) === 0 || strpos( $k, "'" ) === 0 ) )
				{
					$quote = substr( $k, 0, 1 );
					$mult = TRUE;
					$temp[$i] = preg_replace( '/^["\'+]/', '', $k );
				}
				elseif ( $mult && strpos( $k, $quote ) == $length-1 )
				{
					$mult = FALSE;
					$temp[$i++] .= ' ' . preg_replace( '/["\']$/', '', $k );
				}
				elseif ( $mult )
				{
					$temp[$i] .= ' ' . $k;
				}
				else
				{
					$temp[$i++] = $k;
				}
			}
		}
		$this->keywords = $temp;

		# manage the highlight string
		$this->term = $this->EE->functions->var_swap( $this->term, array( 'tag' => $tag, 'class' => $class ) );

		# try the primary text
		if ( empty( $str ) ) $str = strip_tags( $this->EE->TMPL->tagdata );
		$str = ! empty( $str ) ? $this->process( $str ) : $str;
		
		if ( strpos( $str, "$tag class=\"$class\"" )===FALSE &&
			 count( $alternates ) )
		{
			foreach ( $alternates as $temp )
			{
				# search
				$temp = $this->process( strip_tags( $temp ) );
				if ( strpos( $temp, "$tag class=\"$class\"" )!==FALSE )
				{
					$str = $temp;
					break;
				}
			}
		}
		
		$this->return_data = $str;
		return $this->return_data;
		
	} # end Easy_search_summary constructor

	// --------------------------------------------------------------------

	/**
	 * Easy_search_summary::process()
	 * processes the supplied content based on the configuration
	 * 
	 * @param str $str - the content to be parsed
	 * @return str - the processed string
	 */
	function process( $str )
	{
		
		require_once 'Summarizer.php';
		$S = new Summarizer();
		$str = $S->excerpt( $str, $this->keywords );
		
		require_once 'Highlighter.php';
		$H = new Highlighter();
		foreach ( $this->keywords as $k ) $str = $H->process( $str, $k, $this->term );

		return $str;

	} # end Search_summary::process()
		
	// --------------------------------------------------------------------

	/**
	* Easy_search_summary::usage()
	* Describes how the plugin is used
	*/
	function usage()
	{
		ob_start(); ?>
To create nice search summaries, use the following syntax:

	{exp:search_summary hash="{segment_2}"}{content_body}{/exp:search_summary}
	
Where {segment_2} is the search hash in the URL.

By default, the plugin uses HTML5 <code>&lt;mark&gt;</code> elements to wrap the keywords found. You can override that by setting the html version:

	{exp:search_summary hash="{segment_2}" html_version="4"}{content_body}{/exp:search_summary}
	
By default, each found keyword is also classified as a “term,” but you can define your own class as well:

	{exp:search_summary hash="{segment_2}" class="found"}{content_body}{/exp:search_summary}

You can also define (in order of priority) up to 10 alternate fields you’d like to summarize if a match isn’t found in the primary tag:

	{exp:search_summary hash="{segment_2}" alternate_1="{content_sidebar}" alternate_2="{content_footer}"}{content_body}{/exp:search_summary}

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	} # end Easy_search_summary::usage()

} # end Easy_search_summary

/* End of file pi.easy_search_summary.php */ 
/* Location: ./system/expressionengine/third_party/easy_search_summary/pi.easy_search_summary.php */