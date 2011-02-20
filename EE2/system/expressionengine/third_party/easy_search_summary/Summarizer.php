<?php 

class Summarizer {

	var $debug;

	function Summarizer($debug=FALSE)
	{
		$this->debug = $debug;
	}
 
	function excerpt( $text, $words, $length=150, $prefix="&#8230;", $suffix=NULL, $options=array() )
	{
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// Set default score modifiers [tweak away...]
		$options = array_merge( array(
			'exact_case_bonus'  => 2,
			'exact_word_bonus'  => 3,
			'abs_length_weight' => 0.0,
			'rel_length_weight' => 1.0,
			'debug'			  => $this->debug
		), $options);

		// Null suffix defaults to same as prefix
		if ( is_null($suffix) ) $suffix = $prefix;

		// Not enough to work with?
		if ( strlen($text) <= $length ) return $text;

		// Just in case
		if ( ! is_array($words) ) $words = array($words);

		// Build the event list
		// [also calculate maximum word length for relative weight bonus]
		$events = array();
		$maxWordLength = 0;

		foreach ($words as $word) {

			if (strlen($word) > $maxWordLength) $maxWordLength = strlen($word);

			$i = -1;
			while ( ($i = stripos($text, $word, $i+1)) !== false )
			{

				// Basic score for a match is always 1
				$score = 1;

				// Apply modifiers
				// Case matches exactly
				if (substr($text, $i, strlen($word)) == $word) $score += $options['exact_case_bonus'];
				
				// Absolute length weight (longer words count for more)
				if ($options['abs_length_weight'] != 0.0) $score += strlen($word) * $options['abs_length_weight'];
				
				// Relative length weight (longer words count for more)
				if ($options['rel_length_weight'] != 0.0) $score += strlen($word) / $maxWordLength * $options['rel_length_weight'];
				
				// The start of the word matches exactly
				if (preg_match('/\W/', substr($text, $i-1, 1))) $score += $options['exact_word_bonus'];
				
				// The end of the word matches exactly
				if (preg_match('/\W/', substr($text, $i+strlen($word), 1))) $score += $options['exact_word_bonus'];
				

				// Push event occurs when the word comes into range
				$events[] = array(
					'type'  => 'push',
					'word'  => $word,
					'pos'	=> max(0, $i + strlen($word) - $length),
					'score' => $score
				);
				// Pop event occurs when the word goes out of range
				$events[] = array(
					'type'	=> 'pop',
					'word'	=> $word,
					'pos'	=> $i + 1,
					'score'	=> $score
				);
				// Bump event makes it more attractive for words to be in the
				// middle of the excerpt [@todo: this needs work]
				$events[] = array(
					'type'	=> 'bump',
					'word'	=> $word,
					'pos'	=> max(0, $i + floor(strlen($word)/2) - floor($length/2)),
					'score'	=> 0.5
				);
				
			} # end while
			
		} # end foreach

		// If nothing is found then just truncate from the beginning
		if ( empty($events) ) return substr($text, 0, $length) . $suffix;

		// We want to handle each event in the order it occurs in
		// [i.e. we want an event queue]
		$M = new MultiSort();
		$events = $M->sortArray( $events, 'pos', 'rcmp' );

		$scores = array();
		$score	= 0;
		$current_words = array();

		// Process each event in turn
		foreach	 ($events as $idx => $event )
		{
			$thisPos = floor($event['pos']);

			$word = strtolower($event['word']);

			switch ($event['type'])
			{
				case 'push':
					if ( ! isset( $current_words[$word] ) )
					{
						// First occurence of a word gets full value
						$current_words[$word] = 1;
						$score += $event['score'];
					}
					else
					{
						// Subsequent occurrences mean less and less
						$current_words[$word]++;
						$score += $event['score'] / $current_words[$word];
					}
					break;
				case 'pop':
					if ( isset( $current_words[$word] ) )
					{
						if ( $current_words[$word] == 1 )
						{
							unset( $current_words[$word] );
							$score -= ($event['score']);
						}
						else
						{
							$current_words[$word]--;
							if ( $current_words[$word] != 0 ) $score -= $event['score'] / $current_words[$word];
						}
					}
					break;
				case 'bump':
					if (!empty($event['score'])) {
						$score += $event['score'];
					}
					break;
				default:
					break;
			}

			// Close enough for government work...
			$score = round($score, 2);

			// Store the position/score entry
			$scores[$thisPos] = $score;

			// For use with debugging
			$debugWords[$thisPos] = $current_words;

			// Remove score bump
			if ($event['type'] == 'bump') $score -= $event['score'];
		
		} # end foreach ($events as $idx => $event )

		// Calculate the best score
		// Yeah, could have done this in the main event loop
		// but it's better here
		$bestScore = 0;
		foreach ($scores as $pos => $score) if ($score > $bestScore) $bestScore = $score;


		if ( $options['debug'] )
		{
			// This is really quick, really tatty debug information
			// (but it works)
			echo "<table>";
			echo "<caption>Events</caption>";
			echo "<tr><th>Pos</th><th>Type</th><th>Word</th><th>Score</th>";
			foreach ($events as $event)
			{
				echo "<tr>";
				echo "<td>{$event['pos']}</td><td>{$event['type']}</td><td>{$event['word']}</td><td>{$event['score']}</td>";
				echo "</tr>";
			}
			echo "</table>";

			echo "<table>";
			echo "<caption>Positions and their scores</caption>";
			$idx = 0;
			foreach ($scores as $pos => $score)
			{
				$excerpt = substr($text, $pos, $length);
				$style = ($score == $bestScore) ? 'background: #ff7;' : '';

				echo "<tr>";
				echo "<th style=\"$style\">" . $idx . "</th>";
				echo "<td style=\"$style\">" . $pos . "</td>";
				echo "<td style=\"$style\"><div style=\"float: left; width: 2em; margin-right: 1em; text-align right; background: #ddd\">" .
				 	 $score . "</div><code>" . str_repeat('*', $score) . "</code></td>";
				echo "<td style=\"$style\"><table>";
				
				foreach ($debugWords[$pos] as $word => $count) echo "<tr><td>$word</td><td>$count</td></tr>";
				
				echo "</table></td>";
				echo "<td style=\"$style\">" . (
						preg_replace('/(' . implode('|', $words) . ')/i', 
									 '<b style="border: 1px solid red;">\1</b>',
									 htmlentities($excerpt))
					  ) . "</td>";
				echo "</tr>";
				$idx++;
			}
			echo "</table>";
		}


		// Find all positions that correspond to the best score
		$positions = array();
		foreach ($scores as $pos => $score)
		{
		  if ($score == $bestScore) $positions[] = $pos;
		}

		if ( count( $positions ) > 1)
		{
			// Scores are tied => do something clever to choose one
			// @todo: Actually do something clever here
			$pos = $positions[0];
		}
		else
		{
			$pos = $positions[0];
		}

		// Extract the excerpt from the position, (pre|ap)pend the (pre|suf)fix
		$excerpt = htmlspecialchars( substr($text, $pos, $length) );

		if ( $pos > 0 ) $excerpt = $prefix . $excerpt;

		if ( $pos + $length < strlen($text) ) $excerpt .= $suffix;

		return $excerpt;
	
	} # end Summarizer::excerpt
 
} # end Summarizer

class MultiSort {

	function sortArray($array,$parameter,$_function)
	{
		return $this->_uasort($array,$_function,$parameter);
	}

	function cmp ($a, $b, $p)
	{
		return (strcmp ($a[$p],$b[$p]));
	}

	function rcmp ($a, $b, $p)
	{
		return -1 * ( strcmp ( $a[$p], $b[$p] ) );
	}

	function _uasort( $array, $func, $param )
	{
		for( $i=0; $i<sizeof($array); $i++ )
		{
			$tmp = $i;
			for ( $j=1; $j<sizeof($array); $j++ )
			{
				$result = $this->$func($array[$tmp],$array[$j],$param);	   
				if ($result == -1)
				{
					$array = $this->arraySwap( $array, $tmp, $j );
					$tmp = $j;
				} 
			}
		}	 
		return $array;
	}

	function arrayswap($arr, $src, $dst)
	{
		$tmp = $arr[$dst]; 
		$arr[$dst] = $arr[$src]; 
		$arr[$src] = $tmp; 
		return $arr; 
	} 
} # end MultiSort

/* End of file Summarizer.php */ 
/* Location: ./system/expressionengine/third_party/easy_search_summary/Summarizer.php */