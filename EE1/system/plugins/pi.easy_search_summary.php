<?php
/*
=====================================================
 Search Summary - by Easy! Designs, LLC
-----------------------------------------------------
 http://www.easy-designs.net/
=====================================================
 This extension was created by Aaron Gustafson
 - aaron@easy-designs.net
 This work is licensed under the MIT License.
=====================================================
 File: pi.search_summary.php
-----------------------------------------------------
 Purpose: creates a summary from content, based on
 keywords
=====================================================
*/

$plugin_info = array(
	'pi_name'			=> 'Search Summary',
	'pi_version'		=> '1.0',
	'pi_author'			=> 'Aaron Gustafson',
	'pi_author_url'		=> 'http://easy-designs.net/',
	'pi_description'	=> 'Creates a summary from content, based on keywords',
	'pi_usage'			=> Search_summary::usage()
);

class Search_summary {

	var $return_data;
	var $keywords;
  
	# the HTML needed for output
	var $term = '<{tag} class="{class}">{keyword}</{tag}>';

  
	/**
	 * Search_summary constructor
	 * sets any overrides and triggers the processing
	 * 
	 * @param str $str - the content to be parsed
	 */
	function Search_summary ( $str='', $keywords=array(), $html_ver=5, $class=FALSE )
	{
		
		# get any overrides
		global $TMPL, $FNS, $PREFS, $DB;
		
		# get the hash
		$hash = FALSE;
		if ( ! count( $keywords ) && ! $hash )
		{
			$hash = ( $temp = $TMPL->fetch_param('hash') ) ? $temp : array();
		}
		# determine the tag to use
		if ( $temp = $TMPL->fetch_param('html_version') ) $html_ver = $temp;
		$tag = $html_ver==5 ? 'mark' : 'strong';
		# determine the class of the keywords
		if ( ! $class ) $class = ( $temp = $TMPL->fetch_param('class') ) ? $temp : 'term';
		# any alternate content?
		$alternates = array();
		$i = 0;
		while ( $i++ < 10 )
		{
			if ( $temp = $TMPL->fetch_param('alternate_'.$i) ) $alternates[] = $temp;
		}
		
		# find the keywords
		if ( $hash )
		{
			if ( strlen( $hash ) > 32 ) $hash = substr( $hash, 0, 32 );
			$keywords = $DB->query(
				"SELECT `keywords`
				 FROM	`{$PREFS->ini('db_prefix')}_search`
				 WHERE	`search_id` = '{$DB->escape_str($hash)}'"
			)->row['keywords'];
		}
		
		# manage the keywords
		$keywords = explode( ' ', str_replace( '+', '', $keywords ) );
		$i	   = 0;
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
				if ( ! $mult &&
					 ( strpos( $k, '"' ) === 0 ||
					   strpos( $k, "'" ) === 0 ) )
				{
					$quote = substr( $k, 0, 1 );
					$mult = TRUE;
					$temp[$i] = preg_replace( '/^["\'+]/', '', $k );
				}
				elseif ( $mult && 	strpos( $k, $quote ) == $length-1 )
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
		
		# build the highlight string
		$this->term = $FNS->var_swap( $this->term, array( 'tag' => $tag, 'class' => $class ) );
		
		# try the primary text
		if ( empty( $str ) ) $str = strip_tags( $TMPL->tagdata );
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
		
	} # end Search_summary constructor
	
	/**
	 * Search_summary::process()
	 * processes the supplied content based on the configuration
	 * 
	 * @param str $str - the content to be parsed
	 */
	function process( $str )
	{
		require_once 'Summarizer.php';
		$S = new Summarizer();
		$str = $S->excerpt( $str, $this->keywords );
		
		require_once 'Highlighter.php';
		$h = new Highlighter();
		foreach ( $this->keywords as $k )
		{
			$str = $h->process( $str, $k, $this->term );
		}
		
		return $str;
	} # end Search_summary::process()
	  
	/**
	 * Search_summary::usage()
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
/* Location: ./system/expressionengine/plugins/pi.easy_search_summary.php */