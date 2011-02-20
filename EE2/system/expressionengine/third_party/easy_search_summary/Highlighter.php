<?php
/**********************************************************************************************
 * highlighter.php
 **********************************************************************************************
 * This class highlights  text matches a search string (keyword) in html based documents, without destroying html-tags. 
 * This was necessary to scan database-entry written in html by keywords, and display them in another style.
 **********************************************************************************************
 * @name		 Highlighter
 * @description	 Advanced keyword highlighter, keep HTML tags safe.
 * @author(s)	 Bojidar Naydenov a.k.a Bojo (bojo2000@mail.bg) & Antony Raijekov a.k.a Zeos (dev@strategma.bg)
 * @country		 Bulgaria
 * @version		 2.1
 * @copyright	 GPL
 * @access		 public
 *********************************************************************************************/
class Highlighter 
{
	var $keyword;
	var $replacement;
	var $hightlight_bad_tags = array( "A", "IMG" ); //add here more, if you want to filter them 
	
	//constructor
	function Highlighter ( $keyword=FALSE, $replacement=FALSE )
	{
		$this->keyword = $keyword;
		$this->replacement = $replacement;
	} //end func highlight

	//private
	function highlight ( $matches )
	{		
		//check for bad tags and keyword					
		if ( ! in_array( strtoupper($matches[2]), $this->hightlight_bad_tags ) )  
		{
			//put template [replacement]
			$proceed =	preg_replace( "#(".$this->keyword.")#si",
									  str_replace( "{keyword}", $matches[3], $this->replacement ),
									  $matches[0] );
		}
		else	//return as-is
		{
			$proceed = $matches[0];
		}
		return stripslashes( $proceed );
	} //end func hightlighter

	//main api
	function process( $text, $keyword=FALSE, $replacement=FALSE )
	{
		//if there are specific keyword/replacement given
		if ( $keyword != FALSE ) $this->keyword = $keyword;
		if ( $replacement != FALSE ) $this->replacement = $replacement;

		//process text array(&$this, 'method_name'), 
		if ( isset($this->keyword) AND 
			 isset($this->replacement) ):
			return preg_replace_callback( "#(<([A-Za-z]+)[^>]*[\>]*)*(".$this->keyword.")(.*?)(<\/\\2>)*#si",
										  array( &$this, 'highlight' ),
										  $text );
		else:
			return $text;
		endif;
	} //end func process

} // end Highlighter

/* End of file Highlighter.php */ 
/* Location: ./system/expressionengine/third_party/easy_search_summary/Highlighter.php */