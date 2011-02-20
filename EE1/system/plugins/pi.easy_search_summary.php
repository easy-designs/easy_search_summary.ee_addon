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
  'pi_name'        => 'Search Summary',
  'pi_version'     => '1.0',
  'pi_author'      => 'Aaron Gustafson',
  'pi_author_url'	 => 'http://easy-designs.net/',
  'pi_description' => 'Creates a summary from content, based on keywords',
  'pi_usage'       => Search_summary::usage()
);

class Search_summary {

  var $return_data;
  var $keywords;
  
  # the HTML needed for output
  var $term = '<strong class="{class}">{keyword}</strong>';
  
  /**
   * Search_summary constructor
   * sets any overrides and triggers the processing
   * 
   * @param str $str - the content to be parsed
   */
  function Search_summary ( $str='', $keywords=array(), $class=FALSE )
  {
    
    # get any overrides
    global $TMPL, $FNS, $PREFS, $DB;
    
    $hash = FALSE;
    if ( ! count( $keywords ) &&
         ! $hash ) $hash = ( $temp = $TMPL->fetch_param('hash') ) ? $temp : array();
    if ( ! $class ) $class = ( $temp = $TMPL->fetch_param('class') ) ? $temp : 'term';
    
    # find the keywords
    if ( $hash )
    {
      if ( strlen( $hash ) > 32 ) $hash = substr( $hash, 0, 32 );
      $keywords = $DB->query(
        "SELECT `keywords`
         FROM   `{$PREFS->ini('db_prefix')}_search`
         WHERE  `search_id` = '{$DB->escape_str($hash)}'"
      )->row['keywords'];
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
        if ( ! $mult &&
             ( strpos( $k, '"' ) === 0 ||
               strpos( $k, "'" ) === 0 ) )
        {
          $quote = substr( $k, 0, 1 );
          $mult = TRUE;
          $temp[$i] = preg_replace( '/^["\'+]/', '', $k );
        }
        elseif ( $mult &&
                 strpos( $k, $quote ) == $length-1 )
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
    $this->term = $FNS->var_swap( $this->term, array( 'class' => $class ) );
    
    # Fetch string
    if ( empty( $str ) ) $str = strip_tags( $TMPL->tagdata );
    
    # return the processed string
    $this->return_data = ( ! empty( $str ) ? $this->process( $str ) : $str );
  
  } # end Search_summary constructor
  
  /**
   * Search_summary::process()
   * processes the supplied content based on the configuration
   * 
   * @param str $str - the content to be parsed
   */
  function process( $str )
  {
    require_once PATH . '../scripts/classes/Summarizer.php';
    $S = new Summarizer();
    $str = $S->excerpt( $str, $this->keywords );
    
    require_once PATH . '../scripts/classes/Highlighter.php';
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
Want to add some microformats support to your site _and_ gain additional CSS hooks around your images? If so, you've come to the right place. ItFigures comes with a set of sensible default that will allow you to use it right away. By default, it looks for images that sit inside a paragraph by themselves (or, optionally, with additional text).

By implementing it with no options:

{exp:itfigures}{body}{/exp:itfigures}

when the plugin encounters

<p><img src="foo.png" alt=""/></p>

it will remake that as

<div class="figure"><img class="image" src="foo.png" alt="" /></div>

Providing it with additional element hooks in the content allows it to build a more robust figure:

<p><img src="foo.png" alt="" style="float: right;"/> <em>Photo by Aaron</em> <strong>This is a sample image</strong></p>

will become

<div class="figure align-right">
  <img class="image" src="foo.png" alt="" />
  <p class="credit">Photo by Aaron</p>
  <p class="legend">This is a sample image</p>
</div>

making it quite simple to quickly generate figures without having to remember the markup.

If you want to customize things further, you can use any or all of the optional properties:

* left_class: the class for left-alignment ("align-left" by default)
* right_class: the class for right-alignment ("align-right" by default)
* figure_wrap: the wrapper element for the whole figure that we should look for ("p" by default)
* credit_wrap: the wrapper element for the credit ("em" by default)
* legend_wrap: the wrapper element for the legend ("strong" by default)
* figure_el: the element you wish to use as the figure container ("div" by default)
* content_el: the element you wish to use as the text content container (empty by default)
* credit_el: the element you wish to use as the credit container ("p" by default)
* legend_el: the element you wish to use as the legend container ("p" by default)
* output_order: the order in which you want to output the contents into the figure ("img|credit|legend" by default, "img" must come first or last, but cannot come in the middle)

<?php
    $buffer = ob_get_contents();
    ob_end_clean();
    return $buffer;
  } # end ItFigures::usage()

} # end ItFigures

?>